<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
use Modules\Core\Notifications\AccountActivationNotification;
use Modules\Core\Notifications\PasswordResetNotification;

/**
 * CLA-603: the one implementation of "forgot my password", shared by every
 * surface that offers it — the API used by the PWAs (CLA-371) and, since this
 * ticket, the backoffice login screens. Extracted from
 * PasswordResetController so the eligibility rules, the code lifetime and the
 * way a code is validated exist exactly once: the surfaces differ only in the
 * link they email, never in who may reset or how a code is checked.
 *
 * Deliberately NOT Laravel's password_reset_tokens broker (CLA-371): the
 * project reuses the hashed activation-code fields that the account-setup
 * flow already owns, and keeps Microsoft-only accounts out of local password
 * resets entirely.
 */
class PasswordResetService
{
    /** CLA-371: a reset code is valid for one hour. */
    public const CODE_TTL_MINUTES = 60;

    /**
     * CLA-685: the first-password link emailed to a new External worker lasts
     * 72 hours — long enough to survive a weekend between the account being
     * created and its owner reading the mail. Forgot-password stays at one hour.
     */
    public const WELCOME_CODE_TTL_MINUTES = 72 * 60;

    /**
     * Issues a reset code for an eligible account and emails it.
     *
     * Returns quietly for an unknown or ineligible address — the caller's own
     * response must be identical in every case, which is what prevents account
     * enumeration.
     *
     * @param  (callable(User, string): string)|null  $resetUrl  builds the link for the calling surface; null keeps the client-portal default.
     */
    public function sendResetLink(string $email, ?callable $resetUrl = null): void
    {
        $user = User::query()->where('email', $email)->first();

        if (
            ! $user instanceof User
            || ! $user->is_active
            || $user->microsoft_id !== null
            || ! $user->hasCompletedPasswordSetup()
        ) {
            return;
        }

        $code = $this->issueActivationCode($user);

        $user->notify(new PasswordResetNotification(
            $code,
            $resetUrl === null ? null : $resetUrl($user, $code),
        ));
    }

    /**
     * CLA-685: issues a 72-hour code and emails the admin panel's reset link to
     * a provisioned account. Calling it again replaces the stored hash, so the
     * previous link stops working.
     *
     * Like `issueActivationCode()` it decides nothing about eligibility: the
     * caller (CreateUser for External workers, EditUser's resend action) does.
     */
    public function sendActivationEmail(User $user): void
    {
        $code = $this->issueActivationCode($user, self::WELCOME_CODE_TTL_MINUTES);

        $user->notify(new AccountActivationNotification(
            Filament::getPanel('admin')->getResetPasswordUrl($code, $user),
        ));
    }

    /**
     * Mints a one-time code for this account and stores only its hash.
     *
     * Deliberately decides nothing about *who* deserves one: `sendResetLink()`
     * keeps the forgot-password rules, and CLA-653's `core:issue-activation-code`
     * keeps the provisioning ones, which are not the same rules (a brand-new
     * account has no password yet, and that is precisely why it needs a code).
     * What stays shared here is the part that must never drift: the code's
     * length and its hashing; the lifetime is the caller's choice (CLA-685) and
     * defaults to the forgot-password one.
     */
    public function issueActivationCode(User $user, int $ttlMinutes = self::CODE_TTL_MINUTES): string
    {
        $code = Str::random(64);

        $user->forceFill([
            'activation_code_hash' => hash('sha256', $code),
            'activation_code_expires_at' => now()->addMinutes($ttlMinutes),
        ])->saveQuietly();

        return $code;
    }

    /**
     * Consumes a code: sets the new password, clears the code and revokes
     * every token.
     *
     * Throws a ValidationException with one generic reason for any unusable
     * code (unknown, expired, or issued to a Microsoft-only account) so codes
     * cannot be probed — 422 on the API, a field error on the Livewire pages.
     */
    public function resetPassword(string $code, string $password): User
    {
        $hash = hash('sha256', $code);

        return DB::transaction(function () use ($hash, $password): User {
            $user = User::query()
                ->where('activation_code_hash', $hash)
                ->lockForUpdate()
                ->first();

            if (
                ! $user instanceof User
                || ! $user->activation_code_expires_at
                || $user->activation_code_expires_at->isPast()
                || $user->microsoft_id !== null
            ) {
                throw ValidationException::withMessages([
                    'code' => __('core::auth.reset_code_invalid'),
                ]);
            }

            $user->forceFill([
                'password' => $password,
                'password_set_at' => now(),
                'activation_code_hash' => null,
                'activation_code_expires_at' => null,
            ])->save();

            // Full compromise-recovery semantics: unlike an authenticated
            // password change, there is no "current session" to preserve here.
            $user->tokens()->delete();

            return $user;
        });
    }
}
