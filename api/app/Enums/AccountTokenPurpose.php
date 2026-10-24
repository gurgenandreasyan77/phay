<?php

namespace App\Enums;

/** What a one-time link (account_tokens) is for, and how long it lives. */
enum AccountTokenPurpose: string
{
    /** A new login sets its first password. */
    case Invitation = 'invitation';

    /** A forgotten password is replaced. */
    case PasswordReset = 'password_reset';

    public function ttlMinutes(): int
    {
        return match ($this) {
            self::Invitation => (int) config('security.invitation_ttl_minutes'),
            self::PasswordReset => (int) config('security.password_reset_ttl_minutes'),
        };
    }

    /** The admin page the link opens; the token goes after # so it never reaches a server log. */
    public function path(): string
    {
        return match ($this) {
            self::Invitation => '/admin/invite',
            self::PasswordReset => '/admin/reset-password',
        };
    }
}
