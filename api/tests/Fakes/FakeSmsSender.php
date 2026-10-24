<?php

namespace Tests\Fakes;

use App\Security\Sms\SmsSender;

/** SMS in tests: every message is written down instead of sent. */
class FakeSmsSender implements SmsSender
{
    /** @var list<array{to: string, text: string}> */
    public array $sent = [];

    public function send(string $to, string $text): void
    {
        $this->sent[] = ['to' => $to, 'text' => $text];
    }

    public function lastCode(): ?string
    {
        $last = end($this->sent);

        return $last && preg_match('/\d{6}/', $last['text'], $m) ? $m[0] : null;
    }
}
