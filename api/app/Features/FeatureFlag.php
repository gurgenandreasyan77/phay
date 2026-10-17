<?php

namespace App\Features;

/**
 * Registry of every feature flag backed by Laravel Pennant. Flags are per
 * business: Pennant's default scope is the current tenant (see
 * AppServiceProvider), so Feature::active() answers for that business. Adding a new flag only needs a new case here plus its
 * metadata below — no bespoke controller/middleware logic per flag.
 */
enum FeatureFlag: string
{
    case AutoCare = 'auto-care';
    case Market = 'market';

    public function label(): string
    {
        return match ($this) {
            self::AutoCare => 'Ավտոխնամք',
            self::Market => 'Խանութ',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AutoCare => 'Ակտիվացնում կամ ամբողջովին անջատում է ավտոմեքենայի խնամքի բաժինը ողջ հավելվածում։',
            self::Market => 'Ակտիվացնում կամ ամբողջովին անջատում է Խանութ (Market) բաժինը ողջ հավելվածում։',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::AutoCare, self::Market => 'Կատալոգ',
        };
    }

    /**
     * The value Pennant resolves to the first time a flag is checked,
     * before any admin has ever toggled it.
     */
    public function default(): bool
    {
        return match ($this) {
            self::AutoCare, self::Market => true,
        };
    }
}
