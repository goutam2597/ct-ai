<?php

namespace App\Modules\ApiPlatform\Http\Middleware;

use App\Modules\ApiPlatform\Models\ApiKey;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * API authentication (build plan §6.1 API row):
 *
 * - Bearer token vg_… → SHA-256 → api_keys lookup (plaintext never stored).
 * - The key acts as its creator: the workspace context is forced to the key's
 *   workspace with the creator's membership — every permission check and the
 *   BelongsToWorkspace scope then behave exactly like in the panel.
 * - 120 requests a minute per key (documented contract).
 * - Foreign or invalid tokens get a clean 401 JSON, never a leak.
 */
final class AuthenticateApiKey
{
    private const RATE_LIMIT_PER_MINUTE = 120;

    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (! preg_match('/^Bearer\s+(\S+)$/i', $header, $m) || ! str_starts_with($m[1], ApiKey::tokenPrefix())) {
            return $this->unauthorized('Missing or malformed API key.');
        }

        $plaintext = $m[1];

        $key = ApiKey::withoutWorkspaceScope()
            ->where('token_hash', ApiKey::hashToken($plaintext))
            ->first();

        if ($key === null) {
            return $this->unauthorized('Invalid API key.');
        }

        if (! RateLimiter::attempt('api-key:'.$key->id, self::RATE_LIMIT_PER_MINUTE, fn () => null, 60)) {
            return response()->json(['error' => 'Rate limit exceeded (120 requests/minute).'], 429);
        }

        $membership = Membership::query()
            ->where('workspace_id', $key->workspace_id)
            ->where('user_id', $key->created_by)
            ->first();

        if ($membership === null) {
            return $this->unauthorized('API key creator is no longer a member of the workspace.');
        }

        $workspace = $key->workspace()->withoutGlobalScopes()->firstOrFail();

        app(WorkspaceContext::class)->force($workspace, $membership);

        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('api_key', $key);
        $request->attributes->set('api_role', $membership->role ?? WorkspaceRole::Viewer);

        return $next($request);
    }

    private function unauthorized(string $message): Response
    {
        return response()->json(['error' => $message], 401);
    }
}
