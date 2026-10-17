<?php

namespace App\Enums;

enum PostCategory: string
{
    case CleaningTips = 'cleaning-tips';
    case UpholsteryCare = 'upholstery-care';
    case AutoCare = 'auto-care';
    case Guides = 'guides';
    case CompanyNews = 'company-news';
}
