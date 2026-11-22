<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Email verification prompt + signed-link confirmation + resend.
 * Route names match the framework's VerifyEmail notification contract.
 */
final class EmailVerificationController extends Controller
{
    public function prompt(Request $request): Response|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('workspaces.index');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function confirm(Request $request, int $id, string $hash): RedirectResponse
    {
        /** @var User|null $user */
        $user = User::query()->findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403, 'Invalid verification link.');
        }

        if (! $request->hasValidSignature()) {
            abort(403, 'The verification link has expired or was tampered with.');
        }

        if ($request->user()?->id !== $user->id) {
            abort(403, 'Sign in as the account owner to verify this email.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect()->route('workspaces.index')->with('success', 'Email verified.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedEmail()) {
            return redirect()->route('workspaces.index');
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
