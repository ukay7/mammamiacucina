<?php

namespace App\Services;

class EmailArchive
{
    public static function snapshot(string $type, string $body, ?string $attachment = null): array
    {
        $auth = preg_match('/verification|invitation|password|business_approved/', $type);
        $safe = preg_replace_callback('~https?://[^\s<>"\']+~i', static function ($match) use ($auth) {
            return $auth || preg_match('~signature=|token=|/(?:reset-password|verify-email|invite)(?:/|\?)~i', $match[0])
                ? '[Private link hidden]' : $match[0];
        }, $body);
        // Also cover relative or non-HTTP token links in custom templates.
        $safe = preg_replace('~[^\s<>"\']*(?:signature=|token=|/reset-password/)[^\s<>"\']*~i', '[Private link hidden]', $safe);

        return ['body' => $safe, 'redacted' => $safe !== $body,
            'attachment' => $auth ? null : $attachment];
    }
}
