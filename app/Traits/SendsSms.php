<?php
namespace App\Traits;

use App\Services\SmsService;

trait SendsSms
{
public function sendSms(string $to, string $message, string $from = null): array
{
return SmsService::sendSms($to, $message, $from);
}
}
