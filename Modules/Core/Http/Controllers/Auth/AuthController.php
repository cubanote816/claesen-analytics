<?php

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\User;

class AuthController extends Controller
{
    /**
     * Canonical Login endpoint for the Core App.
     * Prepares Identity logic to absorb satellite apps.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        // Accounts without a password (Azure-first provisioning) cannot use local login.
        // Generic message to avoid revealing account existence.
        if (! $user || ! $user->hasCompletedPasswordSetup() || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Return robust canonical structure for the Core App while maintaining strict backwards compatibility with Sport App
        return response()->json([
            'success' => true,
            'accessToken' => $user->createToken($request->device_name ?? config('app.token_name', 'API Token'))->plainTextToken,
            'tokenType' => 'Bearer',
            'expiresAt' => now()->addMinutes(config('sanctum.expiration', 525600))->toDateTimeString(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames() ?? [], // Assuming Spatie roles or similar
            ],
            'message' => 'Login successful',
        ]);
    }

    /**
     * Token introspection endpoint — called by satellite apps to validate Core-issued tokens.
     * Protected by auth:sanctum: invalid tokens never reach this method.
     */
    public function introspect(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'active' => true,
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->toArray(),
            'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
        ]);
    }

    /**
     * Canonical Logout endpoint for the Core App.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }
}
