<?php

use App\Modules\Billing\Gateways\FakeGateway;

/**
 * Billing configuration. The active gateway is ONE setting (build plan §7);
 * additional adapters (Stripe, Paddle, Polar, Lemon Squeezy, Creem, Dodo)
 * plug in here in Phase 3 without touching the pipeline.
 */
return [
    // The fake/offline gateway is for local installs and demos ONLY. It is
    // refused in production unless explicitly enabled (Mono Market gate 2.4).
    'allow_fake_gateway' => (bool) env('CLOT_ALLOW_FAKE_GATEWAY', env('APP_ENV') !== 'production'),

    'default_gateway' => env('CLOT_PAYMENT_GATEWAY', 'fake'),

    'gateways' => [
        'fake' => [
            'label' => 'Offline (demo)',
            'class' => FakeGateway::class,
            'webhook_secret' => env('CLOT_FAKE_WEBHOOK_SECRET', 'fake-secret'),
        ],
    ],

    // Trial days without a card for paid plans.
    'trial_days' => (int) env('CLOT_TRIAL_DAYS', 7),
];
