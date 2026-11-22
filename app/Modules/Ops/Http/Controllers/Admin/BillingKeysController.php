<?php

namespace App\Modules\Ops\Http\Controllers\Admin;

use App\Modules\Ops\Http\Controllers\Controller;
use App\Modules\Ops\Installer\EnvEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Payment provider configuration: which gateways exist, whether their keys
 * are set, and the form that writes them to .env (never rendered back).
 * Values only ever reach .env — the same rule as the installer.
 */
final class BillingKeysController extends Controller
{
    public function index(EnvEditor $env): Response
    {
        $gateways = [];

        foreach (config('clot-billing.gateways') as $key => $gateway) {
            $secretConfigKey = "clot-billing.gateways.$key.secret_key";
            $webhookConfigKey = "clot-billing.gateways.$key.webhook_secret";

            $gateways[] = [
                'key' => $key,
                'label' => $gateway['label'] ?? $key,
                'secretConfigured' => (bool) config($secretConfigKey),
                'webhookSecretConfigured' => (bool) config($webhookConfigKey),
                'webhookUrl' => route('billing.webhook', ['provider' => $key]),
            ];
        }

        return Inertia::render('Admin/BillingKeys', [
            'gateways' => $gateways,
            'envWritable' => $env->exists() || is_writable(dirname((string) config('clot-installer.env_path'))),
        ]);
    }

    public function save(Request $request, EnvEditor $env): RedirectResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', 'in:stripe'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        $gateway = $validated['gateway'];
        $values = [];

        foreach (['secret_key', 'webhook_secret'] as $field) {
            // Only filled inputs are written: empty means "leave as is", never
            // erase a key by accident. Secrets are never rendered back.
            if (! empty($validated[$field])) {
                $envKey = strtoupper('CLOT_'.Str::upper($gateway).'_'.Str::upper($field === 'secret_key' ? 'SECRET_KEY' : 'WEBHOOK_SECRET'));

                // Standard Stripe env names take precedence when the user
                // follows the docs; keep both consistent.
                $values[$gateway === 'stripe'
                    ? ($field === 'secret_key' ? 'STRIPE_SECRET_KEY' : 'STRIPE_WEBHOOK_SECRET')
                    : $envKey] = $validated[$field];
            }
        }

        if ($values !== []) {
            try {
                $env->set($values);
            } catch (RuntimeException $e) {
                return back()->withErrors(['env' => 'Cannot write .env: '.$e->getMessage()]);
            }
        }

        return back()->with('success', 'Keys saved. Restart the queue worker if one is running.');
    }
}
