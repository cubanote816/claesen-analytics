<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Models\User;

class AuthController extends Controller
{
    /**
     * Authenticate the user and establish a session for the mobile SPA.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'device'   => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Suspended, not-yet-activated, or wrong credentials → same generic 401.
        if (! $user || ! $user->is_active || ! $user->hasCompletedPasswordSetup() || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Ongeldige inloggegevens.',
            ], 401);
        }

        if (! $user->hasAnyRole(['project_manager', 'super_admin', 'admin'])) {
            return response()->json([
                'message' => 'Je hebt geen toegang tot de veiligheidsinspecties.',
            ], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'user' => [
                'id'          => $user->id,
                'name'        => $user->name,
                'email'       => $user->email,
                'roles'       => $user->getRoleNames()->values()->toArray(),
                'permissions' => [],
            ],
        ]);
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

    /**
     * Destroy the current session for the mobile SPA.
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
        ]);
    }
}
