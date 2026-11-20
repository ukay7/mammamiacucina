<?php
namespace App\Services\Notifications;

class AttachmentUnavailable extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('An attachment is missing or has changed. No email was sent. Restore the original file or prepare a new draft and test it again.');
    }
}
