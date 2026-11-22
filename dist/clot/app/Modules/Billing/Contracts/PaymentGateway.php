<?php

namespace App\Modules\Billing\Contracts;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Workspace;
use Illuminate\Http\Request;

/**
 * Payment gateway abstraction (build plan §7). One adapter per provider;
 * the active provider is a single setting, exactly like VisiGoo's model.
 *
 * Security contract (Mono Market gate 2.4 — every adapter MUST obey):
 * - Money state changes ONLY through verified, server-side events.
 * - Webhook verification: signature checked BEFORE any state change; the
 *   provider event id is single-use (payment_events unique index).
 * - Amounts are compared against the plan's price server-side, never trusted
 *   from the payload alone.
 * - Sandbox/test mode is explicitly configured; a fake/test gateway must
 *   refuse to run in production.
 */
interface PaymentGateway
{
    public function key(): string;

    /** Start a checkout; for redirect-based gateways returns the URL. */
    public function startCheckout(Workspace $workspace, Plan $plan): ?string;

    /** Verify an inbound webhook request; returns a normalized event or null to reject. */
    public function verifyWebhook(Request $request): ?InboundPayment;

    /** Ask the gateway's own record for the current state (reconciliation). */
    public function reconcile(Subscription $subscription): ?ReconcileResult;
}
