<?php

namespace App\Notifications;

/** Where a notification shows (see AdminNotification::scopeVisibleTo). */
enum NotificationScope: string
{
    /** Inside the business it is about. */
    case Business = 'business';

    /** For platform staff, whichever business they are in. */
    case Platform = 'platform';
}
