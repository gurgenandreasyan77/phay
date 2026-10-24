<?php

namespace App\Security;

use App\Enums\AccountTokenPurpose;
use App\Enums\SecurityEvent;
use App\Mail\InvitationMail;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Makes an invitation or password reset link, emails it, and gives it back
 * so staff can also pass it on by phone/messenger. A mail failure doesn't
 * lose the link: it's reported and `emailed` says so.
 */
class AccountLinks
{
    public function __construct(
        private readonly AccountTokens $tokens,
        private readonly SecurityLog $log,
    ) {}

    /** @return array{url: string, expiresAt: Carbon, emailed: bool} */
    public function invite(User $user, ?User $actor = null, ?int $ttlMinutes = null): array
    {
        $link = $this->tokens->issue($user, AccountTokenPurpose::Invitation, $actor, $ttlMinutes);
        $emailed = $this->mail(fn () => Mail::to($user->email)->send(new InvitationMail($user, $link['url'], $link['expiresAt'])));

        $this->log->record(SecurityEvent::InvitationCreated, $user, ['emailed' => $emailed], $actor);

        return ['url' => $link['url'], 'expiresAt' => $link['expiresAt'], 'emailed' => $emailed];
    }

    /** @return array{url: string, expiresAt: Carbon, emailed: bool} */
    public function passwordReset(User $user, ?User $actor = null): array
    {
        $link = $this->tokens->issue($user, AccountTokenPurpose::PasswordReset, $actor);
        $emailed = $this->mail(fn () => Mail::to($user->email)->send(new PasswordResetMail($user, $link['url'], $link['expiresAt'])));

        $this->log->record(
            $actor ? SecurityEvent::PasswordResetLinkCreated : SecurityEvent::PasswordResetRequested,
            $user,
            ['emailed' => $emailed],
            $actor,
        );

        return ['url' => $link['url'], 'expiresAt' => $link['expiresAt'], 'emailed' => $emailed];
    }

    private function mail(callable $send): bool
    {
        try {
            $send();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
