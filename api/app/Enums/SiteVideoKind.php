<?php

namespace App\Enums;

/** What a video on the business's site shows. */
enum SiteVideoKind: string
{
    /** The result of the work: how it was and how it became. */
    case BeforeAfter = 'before_after';

    /** About the business or the work: how it is done, tips, news. */
    case Info = 'info';
}
