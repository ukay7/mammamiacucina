<?php
return [
    // Email is the first adapter. Future channels add their own preparation and credential policies.
    'channels' => ['email' => \App\Services\Notifications\EmailChannel::class],
    'max_recipients' => 20000,
    'webhook_secret' => env('NOTIFICATIONS_RESEND_WEBHOOK_SECRET'),
];
