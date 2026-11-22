<?php

namespace App\Modules\ApiPlatform\Http\Controllers;

use App\Models\User;
use App\Modules\ApiPlatform\Models\ApiKey;
use App\Modules\Tenancy\Http\Controllers\Controller;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * API keys screen (customer panel, owner/admin): create shows the plaintext
 * token ONCE; revoke deletes. Keys act as their creator member.
 */
final class ApiKeysController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = WorkspaceContext::current()?->workspace();

        if ($workspace === null) {
            abort(403);
        }

        Gate::authorize('view', $workspace);

        $keys = ApiKey::query()->orderByDesc('id')->limit(100)->get()
            ->map(fn (ApiKey $key) => [
                'id' => $key->id,
                'name' => $key->name,
                'creator' => $key->creator->email,
                'lastUsedAt' => $key->last_used_at?->toISOString(),
                'createdAt' => $key->created_at?->toISOString(),
            ]);

        return Inertia::render('ApiKeys/Index', [
            'keys' => $keys,
            'createdToken' => $request->session()->get('created_token'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspace = WorkspaceContext::current()?->workspace();

        if ($workspace === null) {
            abort(403);
        }

        Gate::authorize('manageSettings', $workspace);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $plaintext = ApiKey::tokenPrefix().Str::random(40);

        ApiKey::query()->create([
            'workspace_id' => $workspace->id,
            'created_by' => $user->id,
            'name' => $validated['name'],
            'token_hash' => ApiKey::hashToken($plaintext),
        ]);

        // Shown exactly once on the next page render, then gone.
        return redirect()
            ->route('api-keys.index')
            ->with('created_token', $plaintext);
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $workspace = WorkspaceContext::current()?->workspace();

        if ($workspace === null || $apiKey->workspace_id !== $workspace->id) {
            abort(403);
        }

        Gate::authorize('manageSettings', $workspace);

        $apiKey->delete();

        return back()->with('success', 'API key revoked.');
    }
}
