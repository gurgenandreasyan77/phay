<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication
    |--------------------------------------------------------------------------
    |
    | Platform staff (super admin, moderators) always need it. Owners of a
    | business see its money, so they need it too unless this is turned off;
    | an owner can also require it for everyone in their business.
    |
    */

    'two_factor' => [
        'issuer' => env('TWO_FACTOR_ISSUER', 'Servio'),
        'required_for_owners' => (bool) env('TWO_FACTOR_REQUIRED_FOR_OWNERS', true),
        // How long the code step of a sign-in waits for the code.
        'challenge_ttl_minutes' => 5,
        'challenge_attempts' => 5,
        'recovery_codes' => 8,
    ],

    /*
    |--------------------------------------------------------------------------
    | One-time links and codes
    |--------------------------------------------------------------------------
    */

    'invitation_ttl_minutes' => (int) env('INVITATION_TTL_MINUTES', 60 * 72),
    // The very first super admin, created from the console.
    'bootstrap_ttl_minutes' => (int) env('BOOTSTRAP_TTL_MINUTES', 60),
    'password_reset_ttl_minutes' => (int) env('PASSWORD_RESET_TTL_MINUTES', 60),

    'codes' => [
        'ttl_minutes' => 10,
        'attempts' => 5,
        // A new code can be asked for after this many seconds.
        'resend_after_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Where links point
    |--------------------------------------------------------------------------
    |
    | The admin panel (servio-admin) the invitation and reset links open.
    |
    */

    'admin_url' => rtrim((string) env('ADMIN_URL', 'http://localhost:3001'), '/'),

    /*
    |--------------------------------------------------------------------------
    | SMS
    |--------------------------------------------------------------------------
    |
    | "log" writes the text to the Laravel log (development). "twilio" sends
    | it through Twilio's API.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'twilio' => [
            'sid' => env('TWILIO_ACCOUNT_SID'),
            'token' => env('TWILIO_AUTH_TOKEN'),
            // A sender number/name, or a Messaging Service SID (MG...).
            'from' => env('TWILIO_FROM'),
        ],
    ],

];
