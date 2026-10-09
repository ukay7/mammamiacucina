<?php
namespace App\Services;

use App\Models\{Customer, Product, User, Role, SmtpSetting};
use Illuminate\Support\Facades\{DB, URL};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PosCustomer
{
    public function resolve(int $id, bool $lock = false): Customer
    {
        $query = Customer::whereKey($id);
        $customer = ($lock ? $query->lockForUpdate() : $query)->first();
        $user = $customer ? ($lock ? User::whereKey($customer->user_id)->lockForUpdate()->first() : $customer->user) : null;
        if (!$customer || !$user?->is_active || !$user->isCustomer()) {
            throw ValidationException::withMessages(['customer_id'=>'Select an active customer before adding products.']);
        }
        if ($user->businessApprovalPending()) {
            throw ValidationException::withMessages(['customer_id'=>'This business account is awaiting admin approval. Approve the account before placing an order.']);
        }
        $customer->setRelation('user', $user);
        return $customer;
    }

    public function price(Product $product, Customer $customer): ?int
    {
        $price = $customer->user->account_type === 'business'
            ? ($product->business_selling_price_cad ?? $product->total_selling_price_cad)
            : $product->total_selling_price_cad;
        return $price === null ? null : (int) round((float) $price * 100);
    }

    public function create(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $email = strtolower(trim($data['email']));
            if (User::whereRaw('lower(email) = ?', [$email])->exists()) {
                throw ValidationException::withMessages(['email'=>'This email already has an account. Select the existing customer.']);
            }
            $user = new User;
            $user->forceFill(['name'=>trim($data['first_name'].' '.($data['last_name'] ?? '')), 'email'=>$email,
                'phone'=>$data['phone'] ?? null, 'account_type'=>$data['account_type'], 'is_active'=>true,
                'role_id'=>Role::where('name','Customer')->value('id'), 'password'=>Str::random(64),
                'email_verified_at'=>$data['account_type']==='individual' && SmtpSetting::autoVerifyIndividual() ? now() : null])->save();
            $customer = $user->customerRecord();
            $customer->update(collect($data)->only(array_merge(BusinessDetails::FIELDS, ['address','city','province','postal_code','country']))->all());
            DB::afterCommit(function () use ($user) {
                if ($user->email_verified_at) return;
                $url = URL::temporarySignedRoute('customer.invite',now()->addDays(2),['user'=>$user->id,'hash'=>sha1($user->email)]);
                try { app(OutgoingEmail::class)->template('pos_invitation',$user->email,['verification_url'=>$url,'expires_minutes'=>2880],$user); }
                catch (\Throwable $e) { report($e); }
            });
            return $customer->load('user');
        });
    }
}
