<?php

use Illuminate\Support\Facades\Schedule;

// Collection scheduler (build plan §4.3): one cron entry runs every minute;
// this checks for due brands every 15 minutes.
Schedule::command('clot:sweep-due')->everyFifteenMinutes();

// The morning check (dedup keys make re-runs free).
Schedule::command('clot:alerts-evaluate')->everyFifteenMinutes();

// The Monday digest (08:00).
Schedule::command('clot:digest-send')->weeklyOn(1, '8:00');

// Billing renewals: nightly, plus the cron heartbeat proving cron actually runs.
Schedule::command('clot:billing-renew')->dailyAt('02:00');
Schedule::command('clot:install-cron-ping')->everyMinute();
