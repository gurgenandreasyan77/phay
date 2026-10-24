<?php

namespace App\Enums;

/**
 * What the audit log records about sign-in and account security
 * (security_events.event). Labels are for the admin (Armenian).
 */
enum SecurityEvent: string
{
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case TwoFactorFailed = 'two_factor_failed';
    case RecoveryCodeUsed = 'recovery_code_used';
    case Logout = 'logout';
    case TwoFactorEnabled = 'two_factor_enabled';
    case TwoFactorDisabled = 'two_factor_disabled';
    case TwoFactorReset = 'two_factor_reset';
    case RecoveryCodesRegenerated = 'recovery_codes_regenerated';
    case PasswordChanged = 'password_changed';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordResetLinkCreated = 'password_reset_link_created';
    case PasswordReset = 'password_reset';
    case InvitationCreated = 'invitation_created';
    case InvitationAccepted = 'invitation_accepted';
    case EmailVerified = 'email_verified';
    case PhoneVerified = 'phone_verified';
    case SessionRevoked = 'session_revoked';
    case LoginCreated = 'login_created';
    case LoginRoleChanged = 'login_role_changed';
    case LoginRemoved = 'login_removed';
    case SuperAdminBootstrapped = 'super_admin_bootstrapped';
    case SuperAdminGranted = 'super_admin_granted';
    case BusinessTwoFactorPolicyChanged = 'business_two_factor_policy_changed';

    public function label(): string
    {
        return match ($this) {
            self::LoginSucceeded => 'Մուտք',
            self::LoginFailed => 'Մուտքի անհաջող փորձ',
            self::TwoFactorFailed => 'Սխալ 2FA կոդ',
            self::RecoveryCodeUsed => 'Մուտք վերականգնման կոդով',
            self::Logout => 'Ելք',
            self::TwoFactorEnabled => '2FA-ն միացվեց',
            self::TwoFactorDisabled => '2FA-ն անջատվեց',
            self::TwoFactorReset => '2FA-ն զրոյացվեց',
            self::RecoveryCodesRegenerated => 'Նոր վերականգնման կոդեր',
            self::PasswordChanged => 'Գաղտնաբառը փոխվեց',
            self::PasswordResetRequested => 'Գաղտնաբառի վերականգնման հայց',
            self::PasswordResetLinkCreated => 'Ստեղծվեց վերականգնման հղում',
            self::PasswordReset => 'Գաղտնաբառը վերականգնվեց',
            self::InvitationCreated => 'Ուղարկվեց հրավեր',
            self::InvitationAccepted => 'Հրավերն ընդունվեց',
            self::EmailVerified => 'Էլ. փոստը հաստատվեց',
            self::PhoneVerified => 'Հեռախոսը հաստատվեց',
            self::SessionRevoked => 'Սեսիան փակվեց',
            self::LoginCreated => 'Ստեղծվեց մուտք',
            self::LoginRoleChanged => 'Փոխվեց դերը',
            self::LoginRemoved => 'Մուտքը հեռացվեց',
            self::SuperAdminBootstrapped => 'Ստեղծվեց առաջին սուպեր ադմինը',
            self::SuperAdminGranted => 'Տրվեց սուպեր ադմինի իրավունք',
            self::BusinessTwoFactorPolicyChanged => 'Փոխվեց բիզնեսի 2FA պահանջը',
        };
    }

    /** Worth a look on the platform log: someone may be trying to get in. */
    public function isWarning(): bool
    {
        return in_array($this, [self::LoginFailed, self::TwoFactorFailed, self::RecoveryCodeUsed, self::TwoFactorReset], true);
    }
}
