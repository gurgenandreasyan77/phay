<?php

namespace App\Enums;

/**
 * Platform staff (Phay's own people), stored as users.is_super_admin /
 * users.is_platform_moderator.
 *
 * - super_admin: everything, including modules of a business and the
 *   platform team itself.
 * - moderator: onboards clients: businesses, their sites and logins, and can
 *   enter any business; can't turn modules on/off or manage platform staff.
 */
enum PlatformRole: string
{
    case SuperAdmin = 'super_admin';
    case Moderator = 'moderator';
}
