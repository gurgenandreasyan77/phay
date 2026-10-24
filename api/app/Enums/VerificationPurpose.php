<?php

namespace App\Enums;

/** What a 6-digit code (verification_codes) confirms. */
enum VerificationPurpose: string
{
    case Email = 'email';
    case Phone = 'phone';
}
