<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscription warnings and end notices (also sent when a business opens its admin).
Schedule::command('platform:subscriptions:sync')->dailyAt('10:00')->timezone('Asia/Yerevan');

// Expired admin sessions (sanctum.expiration) are dropped from the table.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
