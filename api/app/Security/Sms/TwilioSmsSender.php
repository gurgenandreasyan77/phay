<?php

namespace App\Security\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Twilio Programmable Messaging. `from` is a sender number, an alphanumeric
 * sender ID (allowed for Armenia) or a Messaging Service SID (MG...).
 */
class TwilioSmsSender implements SmsSender
{
    public function __construct(
        private readonly ?string $sid,
        private readonly ?string $token,
        private readonly ?string $from,
    ) {}

    public function send(string $to, string $text): void
    {
        if (! $this->sid || ! $this->token || ! $this->from) {
            throw new RuntimeException('Twilio is not configured (TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM).');
        }

        $sender = str_starts_with($this->from, 'MG') ? ['MessagingServiceSid' => $this->from] : ['From' => $this->from];

        Http::asForm()
            ->withBasicAuth($this->sid, $this->token)
            ->timeout(10)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                'To' => $to,
                'Body' => $text,
                ...$sender,
            ])
            ->throw();
    }
}
