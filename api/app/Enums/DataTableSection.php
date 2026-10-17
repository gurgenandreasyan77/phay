<?php

namespace App\Enums;

use App\Features\FeatureFlag;

/**
 * Which part of the business admin a custom table lives in. Each one is a
 * module the super admin turns on per business: orders for a business
 * that sells services, sales for one that sells goods.
 */
enum DataTableSection: string
{
    case Orders = 'orders';
    case Sales = 'sales';

    public function feature(): FeatureFlag
    {
        return match ($this) {
            self::Orders => FeatureFlag::Orders,
            self::Sales => FeatureFlag::Sales,
        };
    }
}
