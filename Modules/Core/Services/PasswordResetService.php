<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;
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

        $code = Str::random(64);

        $user->forceFill([
            'activation_code_hash' => hash('sha256', $code),
            'activation_code_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
        ])->saveQuietly();

        $user->notify(new PasswordResetNotification(
            $code,
            $resetUrl === null ? null : $resetUrl($user, $code),
        ));
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
