<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'leads' => [
        'rate_limit_per_minute' => env('LEADS_RATE_LIMIT_PER_MINUTE', 5),
    ],

    // The AI assistant in each business's admin. Without a key the assistant
    // page says it is not connected yet.
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ASSISTANT_MODEL', 'claude-opus-5-5'),
        'effort' => env('ASSISTANT_EFFORT', 'low'),
        'max_tokens' => (int) env('ASSISTANT_MAX_TOKENS', 8000),
        // Requests a subscription month for a business without a plan; each
        // plan sets its own (Platform → Plans, App\Assistant\AssistantQuota).
        'monthly_limit' => (int) env('ASSISTANT_MONTHLY_LIMIT', 30),
    ],

    // Renders the AI agents' designs to PNG (media/server.js, App\Media\HttpRenderer).
    // Without a URL the agents can't render and give briefs instead.
    'media' => [
        'url' => env('MEDIA_URL'),
        'token' => env('MEDIA_TOKEN'),
        'timeout' => (int) env('MEDIA_TIMEOUT', 40),
    ],

    // Google Calendar in the business calendar (read only). Keys of an OAuth
    // client of type "Web application" in Google Cloud; the redirect URI is
    // the admin's /api/admin/google/callback, registered there exactly.
    // Without them the calendar says the sync is not set up yet.
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
    ],

    // Instagram and Facebook (social module), read only, through a Meta app
    // of type Business with Facebook Login. The redirect URI is the admin's
    // /api/admin/social/callback, registered in the app exactly. Without
    // the keys the social screen says the connection is not set up yet.
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
        // A «Facebook Login for Business» configuration id; empty: the
        // permissions are asked by name (App\Social\HttpMetaClient::SCOPES).
        'login_config_id' => env('META_LOGIN_CONFIG_ID'),
    ],

    'admin_login' => [
        'rate_limit_per_minute' => env('ADMIN_LOGIN_RATE_LIMIT_PER_MINUTE', 5),
    ],

];
