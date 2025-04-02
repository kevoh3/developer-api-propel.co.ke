<?php

namespace App\Channels;

use Illuminate\Notifications\Notification;
use App\Services\SmsService;

class SmsChannel
{
    /**
     * Send the given notification via SMS.
     */
    public function send($notifiable, Notification $notification)
    {
        if (!method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if ($notifiable->phone) {
            SmsService::sendSms($notifiable->mobile, $message);
        }
    }
}
