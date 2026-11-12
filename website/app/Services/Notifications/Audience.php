<?php
namespace App\Services\Notifications;

use App\Models\{Customer, NotificationCampaign, NotificationSuppression};
use Illuminate\Validation\ValidationException;

class Audience
{
    public static function customers()
    {
        return Customer::where(fn($q) => $q->whereNull('user_id')->orWhereHas('user', fn($u) => $u->where('is_active', true)->whereIn('account_type', ['business','individual'])));
    }

    public function resolve(NotificationCampaign $campaign): array
    {
        $rows = []; $invalid = 0; $duplicates = 0;
        $add = function ($address, $name, $source, $customerId = null) use (&$rows, &$invalid, &$duplicates) {
            $address = strtolower(trim((string)$address));
            if (!filter_var($address, FILTER_VALIDATE_EMAIL) || strlen($address) > 255) { $invalid++; return; }
            if (isset($rows[$address])) { $duplicates++; return; }
            $rows[$address] = ['address'=>$address, 'recipient_name'=>$name, 'source'=>$source, 'customer_id'=>$customerId];
            if (count($rows) > config('notifications.max_recipients')) throw ValidationException::withMessages(['audience'=>'This list exceeds 20,000 addresses. Split it into smaller campaigns.']);
        };
        $ids = collect($campaign->audience)->filter(fn($v)=>str_starts_with($v,'customer:'))->map(fn($v)=>(int)substr($v,9))->all();
        $customers = self::customers()->when($campaign->audience_mode==='selected', fn($q)=>$q->whereIn('id',$ids))
            ->when($campaign->customer_type!=='all', fn($q)=>$q->where('account_type',$campaign->customer_type));
        $customers->orderBy('id')->chunkById(500, function($customers) use ($campaign, $add) {
            foreach ($customers as $customer) {
                $name = $customer->business_name ?: $customer->name;
                $add($customer->email, $name, 'customer', $customer->id);
                if ($campaign->include_alternate && $customer->alternate_email) $add($customer->alternate_email, $name, 'alternate', $customer->id);
            }
        });
        foreach ($campaign->audience as $value) if (str_starts_with($value,'email:')) $add(substr($value,6), null, 'additional');
        $suppressed = [];
        foreach (array_chunk(array_keys($rows),500) as $addresses) {
            $suppressed += NotificationSuppression::where('channel','email')->whereIn('address',$addresses)->pluck('reason','address')->all();
        }
        foreach ($rows as $address=>&$row) $row['suppression'] = $suppressed[$address] ?? null;
        unset($row);
        return ['rows'=>array_values($rows), 'invalid'=>$invalid, 'duplicates'=>$duplicates, 'suppressed'=>count($suppressed), 'eligible'=>count($rows)-count($suppressed)];
    }
}
