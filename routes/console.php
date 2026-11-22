<?php

use Illuminate\Support\Facades\Schedule;

// Collection scheduler (build plan §4.3): one cron entry runs every minute;
// this checks for due brands every 15 minutes.
Schedule::command('clot:sweep-due')->everyFifteenMinutes();

// The morning check (dedup keys make re-runs free).
Schedule::command('clot:alerts-evaluate')->everyFifteenMinutes();

// Billing renewals: nightly, plus the cron heartbeat proving cron actually runs.
Schedule::command('clot:billing-renew')->dailyAt('02:00');
Schedule::command('clot:install-cron-ping')->everyMinute();
