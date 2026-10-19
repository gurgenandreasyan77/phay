<?php

namespace App\Enums;

/**
 * What a person does inside one business (tenant_user.role). Platform staff
 * (super admin, platform moderator) entering a business act as its owner.
 *
 * - owner: everything, including money (analytics, expenses) and the AI assistant.
 * - admin: runs the business day to day: orders, services, modules,
 *   settings, but not its revenue figures or the paid AI assistant.
 * - moderator: works with orders only.
 */
enum BusinessRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Moderator = 'moderator';

    /** Orders and the dashboard. */
    public const ORDERS = 'orders';

    /** Services, modules (dry cleaning, auto care, shop) and uploads. */
    public const CATALOG = 'catalog';

    /** Business settings and contact channels. */
    public const SETTINGS = 'settings';

    /** Revenue analytics and the AI assistant. */
    public const INSIGHTS = 'insights';

    /** What the business spends (taxes, rent, salaries...) and its net profit. */
    public const EXPENSES = 'expenses';

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => [self::ORDERS, self::CATALOG, self::SETTINGS, self::INSIGHTS, self::EXPENSES],
            self::Admin => [self::ORDERS, self::CATALOG, self::SETTINGS],
            self::Moderator => [self::ORDERS],
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
