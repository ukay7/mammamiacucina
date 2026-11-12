<?php

namespace App\Services;

use App\Support\BusinessTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeliverySchedule
{
    public const FIELDS = ['delivery_date', 'delivery_time', 'delivery_time_reference'];

    public function defaultTime(): CarbonImmutable
    {
        // Add elapsed hours in UTC, then display Toronto's calendar date and time.
        return CarbonImmutable::instance(now('UTC'))->addHours(2)->startOfMinute();
    }

    public function defaults(?CarbonImmutable $time = null): array
    {
        $time ??= $this->defaultTime();
        $local = BusinessTime::local($time);

        return ['delivery_date' => $local->format('Y-m-d'), 'delivery_time' => $local->format('H:i'),
            'delivery_time_reference' => $time->timestamp];
    }

    public function resolve(array $input, bool $delivery, ?CarbonImmutable $unchanged = null): ?CarbonImmutable
    {
        if (! $delivery) return null;

        // Older open checkout forms can still submit using the same two-hour default.
        // Present-but-empty or incomplete selections must be corrected by the user.
        if (! array_key_exists('delivery_date', $input) && ! array_key_exists('delivery_time', $input)) {
            return $this->defaultTime();
        }
        $data = Validator::make($input, [
            'delivery_date' => 'required|date_format:Y-m-d',
            'delivery_time' => 'required|date_format:H:i',
            'delivery_time_reference' => 'nullable|integer|min:0|max:253402300799',
        ], [], ['delivery_date' => 'delivery date', 'delivery_time' => 'delivery time'])->validate();
        $value = $data['delivery_date'].'T'.$data['delivery_time'];
        $time = null;
        if (isset($data['delivery_time_reference'])) {
            $reference = CarbonImmutable::createFromTimestampUTC((int) $data['delivery_time_reference'])->startOfMinute();
            // Preserve the exact default instant during the repeated autumn hour.
            // A reference for a different date/time cannot override the visible fields.
            if (BusinessTime::local($reference)->format('Y-m-d\TH:i') === $value) $time = $reference;
        }
        $time ??= BusinessTime::input($value, 'delivery_time');
        // Editing other order details must not force a new appointment or discard
        // seconds from a historical value that is displayed to minute precision.
        if ($unchanged && $time->equalTo($unchanged->startOfMinute())) return $unchanged;
        if ($time->lt(CarbonImmutable::instance(now('UTC'))->startOfMinute())) {
            throw ValidationException::withMessages(['delivery_time' => 'Choose a delivery date and time that is not in the past (Toronto time).']);
        }

        return $time;
    }
}
