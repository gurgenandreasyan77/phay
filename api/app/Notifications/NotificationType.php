<?php

namespace App\Notifications;

enum NotificationType: string
{
    /** A customer sent an order from the business's site. */
    case NewLead = 'new_lead';

    /** A visitor wrote through the feedback form of the business's site. */
    case Feedback = 'feedback';

    /** A message in the chat between a business and the platform team. */
    case SupportMessage = 'support_message';

    /** The subscription ends soon, or has ended. */
    case Subscription = 'subscription';

    /** The orders of today on the calendar. */
    case TodayOrders = 'today_orders';

    /** The connected Instagram / Facebook access ends soon, or stopped working. */
    case Social = 'social';

    /** An AI agent joined the business's team. */
    case Agent = 'agent';

    /** Who is away today or from tomorrow, today's interviews and birthdays. */
    case Team = 'team';
}
