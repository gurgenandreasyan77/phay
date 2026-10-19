<?php

namespace App\Notifications;

enum NotificationType: string
{
    /** A customer sent an order from the business's site. */
    case NewLead = 'new_lead';

    /** A message in the chat between a business and the platform team. */
    case SupportMessage = 'support_message';

    /** The subscription ends soon, or has ended. */
    case Subscription = 'subscription';

    /** The orders of today on the calendar. */
    case TodayOrders = 'today_orders';
}
