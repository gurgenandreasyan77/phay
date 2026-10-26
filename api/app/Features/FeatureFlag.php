<?php

namespace App\Features;

/**
 * The modules the super admin turns on per business, backed by Laravel
 * Pennant. Flags are per business: Pennant's default scope is the current
 * tenant (see AppServiceProvider), so Feature::active() answers for that
 * business. Adding a new module only needs a new case here plus its
 * metadata below — no bespoke controller/middleware logic per flag.
 *
 * Orders and sales are not modules: every business admin has them. What a
 * business does (dry cleaning, car care...) isn't a module either: it is
 * a category of its own in the Services module.
 */
enum FeatureFlag: string
{
    // The business's catalog of services shown on its site: categories,
    // services, packages, and a price calculator when the business has one.
    case Services = 'services';
    case Market = 'market';
    // The business's site: logo, contacts, videos, before/after photos,
    // blog and the feedback form. On once the platform gives it a site.
    case Site = 'site';
    // The business's Instagram and Facebook through Meta (read only): its
    // figures, posts and followers, competitors' public figures, and the AI
    // assistant's answers about them. Each business connects its own login.
    case Social = 'social';

    public function label(): string
    {
        return match ($this) {
            self::Services => 'Ծառայություններ',
            self::Market => 'Խանութ',
            self::Site => 'Կայքի կառավարում',
            self::Social => 'Սոցցանցեր',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Services => 'Բիզնեսի ծառայությունների կատալոգը՝ կատեգորիաներով (օր.՝ ավտոքիմմաքրում, կահույքի քիմմաքրում), գներով և հաշվիչով, որոնք երևում են նրա կայքում։',
            self::Market => 'Ապրանքների խանութը՝ կատեգորիաներով և SEO էջերով, կայքում և ադմինում։',
            self::Site => 'Կայքի բովանդակությունը. լոգո, կոնտակտներ, «մինչև/հետո» և տեղեկատվական տեսանյութեր, լուսանկարներ, բլոգ և հետադարձ կապի ձև։',
            self::Social => 'Instagram-ի և Facebook-ի միացում. հետևորդներ, դիտումներ, ներգրավվածություն, լավագույն գրառումներ, մրցակիցների հրապարակային թվերը և AI օգնականի խորհուրդները դրանց մասին։',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Services, self::Market => 'Կատալոգ',
            self::Site => 'Կայք',
            self::Social => 'Մարքեթինգ',
        };
    }

    /**
     * The value Pennant resolves to the first time a flag is checked,
     * before any admin has ever toggled it. The default business has its
     * modules on by migrations; a new business gets them off when it is created.
     */
    public function default(): bool
    {
        return match ($this) {
            self::Market => true,
            self::Services, self::Site, self::Social => false,
        };
    }

    /**
     * Whether a business created on the platform starts with it on: none,
     * every module waits for the super admin.
     */
    public function onForNewBusiness(): bool
    {
        return false;
    }
}
