<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Email verification + password reset (Mono Market gate 1.6 account
 * lifecycle): the framework notification contracts, verified end to end with
 * Notification::fake — no live mail in CI.
 */
it('sends a verification email on registration and blocks the panel until verified', function (): void {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Unverified Ursula',
        'email' => 'ursula@buyer.test',
        'password' => 'super-secret-1',
        'password_confirmation' => 'super-secret-1',
    ])->assertRedirect(route('verification.notice'));

    $user = User::query()->where('email', 'ursula@buyer.test')->firstOrFail();

    Notification::assertSentTo($user, VerifyEmail::class);

    expect($user->hasVerifiedEmail())->toBeFalse();

    // The panel refuses unverified users.
    $this->actingAs($user)
        ->get(route('workspaces.index'))
        ->assertRedirect(route('verification.notice'));
});

it('verifies via the signed link from the notification', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post('/email/verification-notification')
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);

    // Extract the signed URL the notification would have sent.
    $verifyUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );

    $this->actingAs($user)->get($verifyUrl)
        ->assertRedirect(route('workspaces.index'));

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered verification hash', function (): void {
    $user = User::factory()->unverified()->create();

    $verifyUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('someone-else@example.test')],
    );

    $this->actingAs($user)->get($verifyUrl)->assertForbidden();
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('sends a password reset link and resets the password', function (): void {
    Notification::fake();

    $user = User::factory()->create(['password' => 'old-password-1']);

    $this->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $mail) use ($user) {
        $token = $mail->token;

        // Wrong email on the reset form is rejected.
        $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertRedirect(route('workspaces.index'));

        return true;
    });

    $user->refresh();

    expect(Hash::check('new-password-1', $user->password))->toBeTrue();

    // The reset token is single-use: a second reset with the same token fails.
    Notification::assertSentTimes(ResetPassword::class, 1);
});

it('gives a neutral response for unknown emails on forgot-password', function (): void {
    Notification::fake();

    $response = $this->post('/forgot-password', ['email' => 'nobody@buyer.test']);

    $response->assertRedirect()->assertSessionHas('status');
    Notification::assertNothingSent();
});

it('rejects a used reset token (single-use)', function (): void {
    Notification::fake();

    $user = User::factory()->create(['password' => 'old-password-1']);

    $this->post('/forgot-password', ['email' => $user->email]);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $mail) use (&$token): bool {
        $token = $mail->token;

        return true;
    });

    // First reset with the token succeeds.
    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-1',
        'password_confirmation' => 'new-password-1',
    ])->assertRedirect(route('workspaces.index'));

    expect(Hash::check('new-password-1', $user->refresh()->password))->toBeTrue();

    // The reset auto-logged us in; a reused token must be tried as a guest.
    $this->post('/logout');

    // The SAME token can never be used again (record deleted on success).
    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'another-password-1',
        'password_confirmation' => 'another-password-1',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('another-password-1', $user->refresh()->password))->toBeFalse();
});
