<?php

namespace App\Enums;

enum LeadFulfillmentType: string
{
    case Delivery = 'delivery';
    case Pickup = 'pickup';
}
