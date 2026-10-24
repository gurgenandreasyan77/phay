<?php

namespace App\Security\Sms;

use Illuminate\Support\Facades\Log;

/** Development: the message goes to storage/logs/laravel.log instead of a phone. */
class LogSmsSender implements SmsSender
{
    public function send(string $to, string $text): void
    {
        Log::info('SMS (log driver, not sent)', ['to' => $to, 'text' => $text]);
    }
}
