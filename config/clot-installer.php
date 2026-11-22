<?php

/**
 * Installer configuration (Mono Market gate 2.2). The installer refuses to
 * run once sealed; paths are configurable so tests can isolate them.
 */
return [
    'lock_path' => env('CLOT_INSTALL_LOCK_PATH', storage_path('app/install.lock')),

    // Heartbeat written by clot:install-cron-ping (scheduled every minute);
    // health checks report whether it is fresh.
    'cron_heartbeat_path' => env('CLOT_CRON_HEARTBEAT_PATH', storage_path('app/cron-heartbeat')),

    'queue_ping_path' => env('CLOT_QUEUE_PING_PATH', storage_path('app/queue-ping')),

    'env_path' => env('CLOT_ENV_PATH', base_path('.env')),

    'php_min' => '8.2',

    'extensions' => ['pdo', 'mbstring', 'openssl', 'curl', 'fileinfo', 'gd', 'zip', 'intl'],
];
