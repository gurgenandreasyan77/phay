<?php

namespace App\Enums;

enum ServiceStatus: string
{
    case Draft = 'draft';
    case ComingSoon = 'coming_soon';
    case Active = 'active';
    case TemporarilyUnavailable = 'temporarily_unavailable';
    case Archived = 'archived';

    /**
     * Statuses a public visitor may ever see (in listings or a direct
     * detail-page visit). Draft/Archived stay invisible and non-indexed.
     */
    public static function publicallyVisible(): array
    {
        return [self::Active, self::ComingSoon, self::TemporarilyUnavailable];
    }

    /**
     * Only Active services can actually be ordered — Coming Soon and
     * Temporarily Unavailable show the page but disable the order CTA.
     */
    public function isOrderable(): bool
    {
        return $this === self::Active;
    }
}
