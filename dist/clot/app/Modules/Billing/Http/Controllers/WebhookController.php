<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Single webhook entry: /webhooks/billing/{provider}. Signature verification
 * happens INSIDE the gateway adapter before any state change (gate 2.4).
 * Replays and unknown references are acknowledged harmlessly; wrong amounts
 * are recorded as failed events.
 */
final class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, BillingService $billing): Response
    {
        $gateway = $billing->gateway($provider);

        $payment = $gateway->verifyWebhook($request);

        if ($payment === null) {
            return response('Signature verification failed.', 400);
        }

        $billing->processInbound($payment, $provider);

        // Always 2xx after verification so gateways stop retrying; failed
        // amounts are recorded, replays are de-duplicated upstream.
        return response('OK');
    }
}
