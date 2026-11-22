<?php

namespace App\Modules\Tenancy\Http\Middleware;

use App\Models\User;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the per-request workspace context for authenticated users.
 * Guests pass through untouched (routes below decide auth requirements).
 */
class SetWorkspaceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user instanceof User) {
            app(WorkspaceContext::class)->resolveFor($user);
        }

        return $next($request);
    }
}
