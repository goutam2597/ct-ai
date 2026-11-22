<?php

use Illuminate\Support\Facades\Schedule;

// Collection scheduler (build plan §4.3): one cron entry runs every minute;
// this checks for due brands every 15 minutes.
Schedule::command('clot:sweep-due')->everyFifteenMinutes();
