<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\User;

class ExchangeActivationCodeController extends Controller
{
    public function exchange(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:64']);

        $hash = hash('sha256', $request->code);

        return DB::transaction(function () use ($hash): JsonResponse {
            // lockForUpdate prevents a concurrent request from reading the same row
            // before the first one clears the code.
            $user = User::where('activation_code_hash', $hash)
                ->lockForUpdate()
                ->first();

            // Single generic message prevents enumeration oracle.
            if (
                ! $user ||
                ! $user->activation_code_expires_at ||
                $user->activation_code_expires_at->isPast()
            ) {
                abort(422, 'Invalid or expired activation code.');
            }

            if ($user->hasCompletedPasswordSetup()) {
                abort(409, 'Account already activated.');
            }

            // Invalidate code atomically within this transaction.
            $user->forceFill([
                'activation_code_hash'       => null,
                'activation_code_expires_at' => null,
            ])->saveQuietly();

            // Revoke any stale setup tokens.
            $user->tokens()->where('name', 'password-setup')->delete();

            $setupToken = $user->createToken(
                'password-setup',
                ['setup:password'],
                now()->addMinutes(10)
            )->plainTextToken;

            return response()->json([
                'setup_token' => $setupToken,
                'expires_in'  => 600,
            ]);
        });
    }
}
