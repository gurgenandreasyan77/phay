<?php

namespace App\Enums;

enum ContactChannelType: string
{
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Telegram = 'telegram';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Tiktok = 'tiktok';
    case Viber = 'viber';
    case Email = 'email';
    case Other = 'other';
}
