<?php

namespace App\Services;

use App\Models\{GeneralSetting, Inventory, InventoryMovement, Order, Product};
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosSale
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['sale' => $message]);
    }

    private function lines(array $items, bool $lock = false): array
    {
        $query = Product::whereIn('id', array_column($items, 'id'))->orderBy('id');
        $products = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        if ($products->count() !== count($items)) {
            $this->fail('A product was removed. Review the sale.');
        }
        $lines = [];
        foreach (collect($items)->sortBy('id') as $item) {
            $product = $products[$item['id']];
            if (!$product->is_active || $product->total_selling_price_cad === null || (float) $product->total_selling_price_cad < 0) {
                $this->fail($product->premium_marketing_name.' is inactive or has no selling price.');
            }
            $query = Inventory::where('product_id', $product->id);
            $inventory = ($lock ? $query->lockForUpdate() : $query)->first();
            if ($inventory && $inventory->quantity_on_hand !== null && (float) $inventory->quantity_on_hand < $item['quantity']) {
                $this->fail('Not enough stock for '.$product->premium_marketing_name.'. Available: '.$inventory->quantity_on_hand);
            }
            $lines[] = ['product' => $product, 'inventory' => $inventory, 'quantity' => (int) $item['quantity'],
                'unit_cents' => (int) round((float) $product->total_selling_price_cad * 100)];
        }
        return $lines;
    }

    private function snapshot(array $lines): array
    {
        return array_map(fn ($line) => ['id' => $line['product']->id, 'quantity' => $line['quantity'], 'unit_cents' => $line['unit_cents']], $lines);
    }

    public function quote(array $items, string $fulfillment, int $userId, array $destination=[]): array
    {
        $lines = $this->lines($items);
        $settings = GeneralSetting::findOrFail(1);
        $subtotal = array_sum(array_map(fn ($line) => $line['unit_cents'] * $line['quantity'], $lines));
        if ($subtotal > 999999999) $this->fail('This sale exceeds the supported total.');
        $route=[];
        if($fulfillment==='delivery' && $settings->matrix_delivery_enabled){
            app(DeliveryQuote::class)->options($settings->warehouse_postal_code??'',$destination['postal_code']??'',$destination['country']??'');
            $route=app(DeliveryQuote::class)->quote($settings->warehouse_postal_code??'',$destination['postal_code']??'',$destination['delivery_service']??'');
        }
        $delivery = $fulfillment === 'delivery' ? ($route['delivery_cents']??$settings->delivery_cents) : 0;
        $tax = $settings->taxFor($subtotal);
        $data = ['token' => (string) Str::uuid(), 'user_id' => $userId, 'expires' => now()->addMinutes(30)->timestamp,
            'matrix'=>(bool)$settings->matrix_delivery_enabled,'delivery_route'=>$route,'items' => $this->snapshot($lines), 'fulfillment' => $fulfillment, 'delivery' => $delivery, 'rate' => $settings->tax_basis_points];
        return ['delivery_route'=>$route,'quote' => Crypt::encryptString(json_encode($data)), 'subtotal' => $subtotal, 'delivery' => $delivery,
            'tax' => $tax, 'total' => $subtotal + $delivery + $tax, 'tax_percent' => $settings->tax_basis_points / 100,
            'items' => array_map(fn ($line) => ['name' => $line['product']->premium_marketing_name, 'quantity' => $line['quantity'],
                'unit_cents' => $line['unit_cents']], $lines)];
    }

    public function complete(string $encrypted, array $customer, int $userId): Order
    {
        try {
            $quote = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->fail('Invalid sale review. Review the sale again.');
        }
        if (($quote['user_id'] ?? null) !== $userId) $this->fail('This sale review belongs to another staff member.');
        return DB::transaction(function () use ($quote, $customer, $userId) {
            // A shared lock serializes checkout against stock and settings changes. A retry never deducts twice.
            $settings = GeneralSetting::lockForUpdate()->findOrFail(1);
            if ($existing = Order::where('checkout_token', $quote['token'])->first()) {
                if ($existing->source !== 'pos' || (int) $existing->created_by !== $userId) $this->fail('Invalid sale reference.');
                return $existing;
            }
            if ($quote['expires'] < now()->timestamp) $this->fail('Sale review expired. Review the sale again.');
            $route=[];
            if($quote['fulfillment']==='delivery'){
                if(($quote['matrix']??false)!==(bool)$settings->matrix_delivery_enabled)$this->fail('Delivery settings changed. Review the sale again.');
                if($settings->matrix_delivery_enabled){
                    app(DeliveryQuote::class)->options($settings->warehouse_postal_code??'',$customer['postal_code']??'',$customer['country']??'');
                    $route=app(DeliveryQuote::class)->quote($settings->warehouse_postal_code??'',$customer['postal_code']??'',$customer['delivery_service']??'');
                    if(($quote['delivery_route']??[])!==$route)$this->fail('Delivery destination, service or price changed. Review the sale again before collecting payment.');
                    $customer['postal_code']=$route['delivery_to_postal'];
                }
            }
            $delivery = $quote['fulfillment'] === 'delivery' ? ($route['delivery_cents']??$settings->delivery_cents) : 0;
            if ($quote['rate'] !== $settings->tax_basis_points || $quote['delivery'] !== $delivery) {
                $this->fail('Delivery or tax changed. Review the sale again before collecting payment.');
            }
            $lines = $this->lines($quote['items'], true);
            if ($this->snapshot($lines) !== $quote['items']) $this->fail('A price changed. Review the sale again before collecting payment.');
            $subtotal = array_sum(array_map(fn ($line) => $line['unit_cents'] * $line['quantity'], $lines));
            $pickup = $quote['fulfillment'] === 'pickup';
            if (!$pickup) {
                foreach (['first_name', 'phone', 'address', 'city', 'province', 'postal_code', 'country'] as $field) {
                    if (empty($customer[$field])) $this->fail('Customer name, phone and full address are required for delivery.');
                }
            }
            if ($pickup && $customer['payment_status'] !== 'paid') $this->fail('Record payment received before completing a counter sale.');
            $profile=null;
            if(!empty($customer['customer_id'])){
                $profile=\App\Models\Customer::findOrFail($customer['customer_id']);
                if(!$profile->user->is_active || !$profile->user->isCustomer())$this->fail('This customer account is inactive.');
                $parts=explode(' ',trim($profile->name),2);
                $customer['email']=$profile->email;
                $customer['first_name']=$parts[0];$customer['last_name']=$parts[1]??'';
                $customer['phone']=$profile->phone;
            } elseif(!empty($customer['email'])){
                $email=strtolower(trim($customer['email']));
                $user=\App\Models\User::whereRaw('lower(email) = ?',[$email])->first();
                if($user)$this->fail('This email already has an account. Select the existing customer.');
                $business=\Illuminate\Support\Facades\Validator::make($customer, BusinessDetails::rules(($customer['account_type']??'individual')==='business'))->validate();
                $user=new \App\Models\User();
                $user->forceFill(['name'=>trim(($customer['first_name']??'').' '.($customer['last_name']??'')) ?: $email,'email'=>$email,'phone'=>$customer['phone']??null,'account_type'=>$customer['account_type']??'individual','role_id'=>\App\Models\Role::where('name','Customer')->value('id'),'is_active'=>true,'password'=>Str::random(64)])->save();
                $profile=$user->customerRecord();
                $profile->update(\Illuminate\Support\Arr::only($business,BusinessDetails::FIELDS));
                $customer['email']=$email;
                DB::afterCommit(function()use($user){
                    $url=\Illuminate\Support\Facades\URL::temporarySignedRoute('customer.invite',now()->addDays(2),['user'=>$user->id,'hash'=>sha1($user->email)]);
                    try {
                        app(\App\Services\OutgoingEmail::class)->raw("Your Mamma Mia Cucina customer account is ready. Verify your email:\n".$url."\nThen use Forgot password to set your password and view your orders.",fn($m)=>$m->to($user->email)->subject('Verify your customer account'),'pos_invitation',$user);
                    } catch(\Throwable $e){report($e);}
                });
            }
            if($profile && !$pickup)$profile->update(\Illuminate\Support\Arr::only($customer,['address','city','province','postal_code','country']));
            $details = [];
            foreach (['first_name','last_name','email','phone','address','city','province','postal_code','country'] as $field) {
                $details[$field] = $customer[$field] ?? '';
            }
            $details['first_name'] = $details['first_name'] ?: 'Walk-in customer';
            if ($pickup) {
                foreach (['address','city','province','postal_code','country'] as $field) $details[$field] = '';
            }
            $order = Order::create($details + $route + [
                'customer_id'=>$profile?->id, 'checkout_token' => $quote['token'], 'number' => 'POS-'.Str::ulid(),
                'notes' => $customer['notes'] ?? null, 'source' => 'pos', 'created_by' => $userId,
                'fulfillment' => $quote['fulfillment'], 'status' => 'completed',
                'subtotal_cents' => $subtotal, 'delivery_cents' => $delivery, 'tax_cents' => $settings->taxFor($subtotal), 'tax_basis_points'=>$settings->tax_basis_points,
                'payment_method' => $customer['payment_method'], 'payment_status' => $customer['payment_status'],
                'paid_at' => $customer['payment_status'] === 'paid' ? now() : null,
            ]);
            $order->update(['number' => 'mmc-'.$order->id]);
            foreach ($lines as $line) {
                ['product' => $product, 'inventory' => $inventory, 'quantity' => $quantity, 'unit_cents' => $price] = $line;
                $tracked = $inventory && $inventory->quantity_on_hand !== null;
                $order->items()->create(['product_id' => $product->id, 'name' => $product->premium_marketing_name,
                    'product_code' => $product->product_code, 'qr_code' => $product->qr_code, 'stock_deducted' => $tracked,
                    'quantity' => $quantity, 'unit_cents' => $price, 'line_cents' => $quantity * $price]);
                if ($tracked) {
                    $before = $inventory->quantity_on_hand;
                    $after = ((int) round((float) $before * 1000) - $quantity * 1000) / 1000;
                    $inventory->update(['quantity_on_hand' => $after]);
                    InventoryMovement::create(['product_id' => $product->id, 'quantity_change' => -$quantity,
                        'quantity_before' => $before, 'quantity_after' => $after, 'reason' => 'POS order '.$order->number,
                        'created_by' => $userId, 'created_at' => now()]);
                }
            }
            $order->events()->create(['user_id' => $userId, 'description' => 'Quick Sale created · '.($pickup ? 'Pick up' : 'Delivery').' · '.$customer['payment_method'].' '.$customer['payment_status'], 'created_at' => now()]);
            return $order;
        }, 3);
    }
}
