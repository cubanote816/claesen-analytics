<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Services\PasswordResetService;

// CLA-371: reuses the same hashed-code + expiry fields and DB-lockForUpdate
// pattern already proven by ExchangeActivationCodeController, rather than
// wiring Laravel's separate password_reset_tokens broker. Account-setup
// (activation) and password-reset are mutually exclusive states — an account
// mid-setup was never "activated" yet to have a password to forget, and a
// fully set-up account is never mid-activation — so reusing
// activation_code_hash/activation_code_expires_at for both is safe.
//
// CLA-603: the rules and the code handling moved to PasswordResetService,
// which the backoffice login screens now share; this controller is only the
// HTTP/JSON face of it (its default link keeps pointing at the client portal).
class PasswordResetController extends Controller
{
    public function __construct(private readonly PasswordResetService $passwordReset) {}

    /**
     * Always responds identically regardless of whether the email exists,
     * belongs to a Microsoft-only account, or is still mid-activation —
     * prevents account enumeration.
     */
    public function sendLink(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $this->passwordReset->sendResetLink((string) $request->input('email'));

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

        $this->passwordReset->resetPassword(
            (string) $request->input('code'),
            (string) $request->input('password'),
        );

        return response()->json(['message' => 'Password reset successfully. You can now log in.']);
    }
}
