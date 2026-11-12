<?php
namespace App\Services\Notifications;
use App\Models\{NotificationDelivery, SmtpSetting};
interface Channel
{
    public function send(NotificationDelivery $delivery, SmtpSetting $settings): string;
}
