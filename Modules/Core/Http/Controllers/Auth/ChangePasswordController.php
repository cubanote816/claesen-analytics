<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Http\Requests\ChangePasswordRequest;

class ChangePasswordController extends Controller
{
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->microsoft_id !== null) {
            return response()->json([
                'message' => 'Password management is handled by Microsoft for your account.',
            ], 403);
        }

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors'  => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $currentToken = $request->user()->currentAccessToken();

        $user->update([
            'password'        => $request->password,
            'password_set_at' => now(),
        ]);

        // Revoke all other tokens; skip when token is transient (e.g. actingAs in tests).
        if ($currentToken instanceof PersonalAccessToken) {
            $user->tokens()->where('id', '!=', $currentToken->id)->delete();
        }

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
