<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email/password sign-in with per-IP+email throttling (Mono Market security
 * gate: rate limits on auth endpoints). No lockout messages that leak which
 * emails have accounts.
 */
final class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts('auth:'.$throttleKey, 5)) {
            $seconds = RateLimiter::availableIn('auth:'.$throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        if (Auth::attempt(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            (bool) $request->boolean('remember'),
        )) {
            RateLimiter::clear('auth:'.$throttleKey);
            $request->session()->regenerate();

            $user = Auth::user();

            if ($user instanceof User) {
                app(WorkspaceContext::class)->resolveFor($user);
            }

            return redirect()->intended(route('workspaces.index'));
        }

        RateLimiter::hit('auth:'.$throttleKey, 60);

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
