<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tenancy\Services\WorkspaceCreator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Self-serve sign-up: user + default workspace (free plan) in one go.
 * Email verification lands with the mail sprint; sign-up is rate limited.
 */
final class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request, WorkspaceCreator $workspaces): RedirectResponse
    {
        $throttleKey = 'register:'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds]),
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'workspace' => ['nullable', 'string', 'max:120'],
        ]);

        RateLimiter::hit($throttleKey, 600);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $workspaces->create($user->id, $validated['workspace'] ?? $validated['name']."'s workspace");

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('workspaces.index');
    }
}
