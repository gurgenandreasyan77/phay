<?php

namespace App\Enums;

use App\Features\FeatureFlag;
use App\Models\User;
use App\Tenancy\TenantContext;
use Laravel\Pennant\Feature;

/**
 * Which part of the business admin a custom table lives in. Orders and
 * sales are modules the super admin turns on per business (orders for a
 * business that sells services, sales for one that sells goods) and bring
 * money in. Expenses are what the business spends (taxes, rent, stock...):
 * every business has them, so there's no module, but only roles that may
 * see them (BusinessRole::EXPENSES) reach their tables.
 */
enum DataTableSection: string
{
    case Orders = 'orders';
    case Sales = 'sales';
    case Expenses = 'expenses';

    /** The module that turns the section on; expenses have none. */
    public function feature(): ?FeatureFlag
    {
        return match ($this) {
            self::Orders => FeatureFlag::Orders,
            self::Sales => FeatureFlag::Sales,
            self::Expenses => null,
        };
    }

    /** On for the current business. */
    public function isActive(): bool
    {
        return ($feature = $this->feature()) === null || Feature::active($feature->value);
    }

    /** Rows of the section bring money in (orders, sales), not spend it. */
    public function isRevenue(): bool
    {
        return $this !== self::Expenses;
    }

    /** What a role needs to read and fill the section's tables. */
    public function permission(): string
    {
        return $this === self::Expenses ? BusinessRole::EXPENSES : BusinessRole::ORDERS;
    }

    /** Whether this person may work with the section in the current business. */
    public function allows(?User $user, string $permission): bool
    {
        return (bool) $user?->businessRole(app(TenantContext::class)->current())?->can($permission);
    }

    /** @return list<self> orders and sales: where the money comes from */
    public static function revenue(): array
    {
        return array_values(array_filter(self::cases(), fn (self $s) => $s->isRevenue()));
    }
}
