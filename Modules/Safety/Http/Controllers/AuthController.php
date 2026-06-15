<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\User;

class AuthController extends Controller
{
    /**
     * Authenticate the user and issue an API token for the mobile SPA.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Ongeldige inloggegevens.',
            ], 401);
        }

        if (! $user->hasAnyRole(['project_manager', 'super_admin', 'admin'])) {
            return response()->json([
                'message' => 'Je hebt geen toegang tot de veiligheidsinspecties.',
            ], 403);
        }

        // Revoke all existing mobile tokens for this user to ensure only 1 active session
        $user->tokens()->where('name', 'mobile-app')->delete();

        $deviceName = $validated['device'] ?? 'mobile-app';

        // Issue a plain-text token with the explicit ability marker
        $token = $user->createToken($deviceName, ['role:safety-access']);

        return response()->json([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user'       => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->getRoleNames()->values()->toArray(),
                'permissions' => [],
            ],
        ], 200);
    }

    /**
     * Get the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'          => $user->id,
            'name'        => $user->name,
            'email'       => $user->email,
            'roles'       => $user->getRoleNames()->values()->toArray(),
            'permissions' => [],
        ]);
    }
}
