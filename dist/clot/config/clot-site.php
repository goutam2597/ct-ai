<?php

/**
 * Public site + lead-magnet configuration. The free check spends real
 * provider answers, so it is triple-capped (per email, per IP, globally per
 * day) on top of the installation daily spend cap.
 */
return [
    'free_check_engine' => env('CLOT_FREE_CHECK_ENGINE', 'chatgpt'),
    'free_check_max_per_email' => (int) env('CLOT_FREE_CHECK_MAX_PER_EMAIL', 3),
    'free_check_max_per_ip' => (int) env('CLOT_FREE_CHECK_MAX_PER_IP', 5),
    'free_check_max_per_day' => (int) env('CLOT_FREE_CHECK_MAX_PER_DAY', 25),
];
