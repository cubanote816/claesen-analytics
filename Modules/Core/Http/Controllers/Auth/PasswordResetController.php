<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Models\User;
use Modules\Core\Notifications\PasswordResetNotification;

// CLA-371: reuses the same hashed-code + expiry fields and DB-lockForUpdate
// pattern already proven by ExchangeActivationCodeController, rather than
// wiring Laravel's separate password_reset_tokens broker. Account-setup
// (activation) and password-reset are mutually exclusive states — an account
// mid-setup was never "activated" yet to have a password to forget, and a
// fully set-up account is never mid-activation — so reusing
// activation_code_hash/activation_code_expires_at for both is safe.
class PasswordResetController extends Controller
{
    /**
     * Always responds identically regardless of whether the email exists,
     * belongs to a Microsoft-only account, or is still mid-activation —
     * prevents account enumeration.
     */
    public function sendLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (
            $user
            && $user->is_active
            && $user->microsoft_id === null
            && $user->hasCompletedPasswordSetup()
        ) {
            $code = Str::random(64);

            $user->forceFill([
                'activation_code_hash' => hash('sha256', $code),
                'activation_code_expires_at' => now()->addMinutes(60),
            ])->saveQuietly();

            $user->notify(new PasswordResetNotification($code));
        }

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|size:64',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $hash = hash('sha256', $request->code);

        return DB::transaction(function () use ($hash, $request): JsonResponse {
            $user = User::where('activation_code_hash', $hash)
                ->lockForUpdate()
                ->first();

            // Single generic message prevents enumeration/probing of codes.
            if (
                ! $user
                || ! $user->activation_code_expires_at
                || $user->activation_code_expires_at->isPast()
                || $user->microsoft_id !== null
            ) {
                abort(422, 'Invalid or expired reset code.');
            }

            $user->forceFill([
                'password' => $request->password,
                'password_set_at' => now(),
                'activation_code_hash' => null,
                'activation_code_expires_at' => null,
            ])->save();

            // Full compromise-recovery semantics: unlike an authenticated
            // password change, there is no "current session" to preserve here.
            $user->tokens()->delete();

            return response()->json(['message' => 'Password reset successfully. You can now log in.']);
        });
    }
}
