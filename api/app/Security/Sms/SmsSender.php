<?php

namespace App\Security\Sms;

/** Sends a text message; bound in AppServiceProvider by security.sms.driver. */
interface SmsSender
{
    /** @param string $to E.164, e.g. +37455123456 */
    public function send(string $to, string $text): void;
}
