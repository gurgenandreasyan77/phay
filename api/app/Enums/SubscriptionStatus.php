<?php

namespace App\Enums;

/**
 * Where a business's subscription stands now (see Subscription::status()).
 * Only Expired locks the business's people out of the admin.
 */
enum SubscriptionStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Free = 'free';
    // The super admin turned it off from a date that hasn't come yet.
    case Canceling = 'canceling';
    case Expired = 'expired';
    case Unlimited = 'unlimited';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Փորձաշրջան',
            self::Active => 'Վճարված',
            self::Free => 'Անվճար մուտք',
            self::Canceling => 'Անջատվում է',
            self::Expired => 'Ավարտված',
            self::Unlimited => 'Անժամկետ',
        };
    }

    public function allowsAccess(): bool
    {
        return $this !== self::Expired;
    }
}
