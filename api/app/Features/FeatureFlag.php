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
    // What the business sells: services (orders) and/or goods (sales).
    case Orders = 'orders';
    case Sales = 'sales';
    case AutoCare = 'auto-care';
    case Market = 'market';
    case DryCleaning = 'dry-cleaning';

    public function label(): string
    {
        return match ($this) {
            self::AutoCare => 'Ավտոխնամք',
            self::Market => 'Խանութ',
            self::DryCleaning => 'Քիմմաքրում',
            self::Orders => 'Պատվերներ',
            self::Sales => 'Վաճառքներ',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AutoCare => 'Ակտիվացնում կամ ամբողջովին անջատում է ավտոմեքենայի խնամքի բաժինը ողջ հավելվածում։',
            self::Market => 'Ակտիվացնում կամ ամբողջովին անջատում է Խանութ (Market) բաժինը ողջ հավելվածում։',
            self::DryCleaning => 'Փափուկ կահույքի քիմմաքրման հաշվիչը, դրա սակագները և լրացուցիչ ծառայությունները։',
            self::Orders => 'Ծառայություն մատուցող բիզնեսի համար՝ կայքից եկած պատվերները և պատվերների սեփական աղյուսակները։',
            self::Sales => 'Ապրանք վաճառող բիզնեսի համար՝ վաճառքների սեփական աղյուսակներ, ձեռքով կամ ֆայլից ներմուծմամբ։',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::AutoCare, self::Market, self::DryCleaning => 'Կատալոգ',
            self::Orders, self::Sales => 'Բիզնեսի տեսակ',
        };
    }

    /**
     * The value Pennant resolves to the first time a flag is checked,
     * before any admin has ever toggled it.
     */
    public function default(): bool
    {
        return match ($this) {
            self::AutoCare, self::Market, self::Orders => true,
            // Servio has it on by a migration; anyone else starts without it.
            self::DryCleaning, self::Sales => false,
        };
    }

    /**
     * Whether a business created on the platform starts with it on. Most
     * modules wait for the super admin; orders stay on so a new business
     * gets what every business had before they became a module.
     */
    public function onForNewBusiness(): bool
    {
        return $this === self::Orders;
    }
}
