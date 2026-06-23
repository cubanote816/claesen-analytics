<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Auth;

use Filament\Facades\Filament;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Http\Requests\SetPasswordRequest;

class SetupPasswordController extends Controller
{
    // Web: show the setup form (Filament flow post-Azure).
    public function show(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasCompletedPasswordSetup()) {
            return redirect()->intended(Filament::getUrl());
        }

        return view('core::auth.setup-password');
    }

    // Web: save the password from the Blade form.
    public function store(SetPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasCompletedPasswordSetup()) {
            return redirect()->intended(Filament::getUrl());
        }

        $user->update([
            'password'        => $request->password,
            'password_set_at' => now(),
        ]);

        // Revoke any dangling setup tokens for this user.
        $user->tokens()->where('name', 'password-setup')->delete();

        return redirect()->intended(Filament::getUrl());
    }

    // API: save the password using the setup:password Sanctum token.
    public function setupViaToken(SetPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasCompletedPasswordSetup()) {
            return response()->json(['message' => 'Account already activated.'], 409);
        }

        $user->update([
            'password'        => $request->password,
            'password_set_at' => now(),
        ]);

        // Revoke all setup tokens including the one used for this request.
        $user->tokens()->where('name', 'password-setup')->delete();

        return response()->json(['message' => 'Password set successfully. You can now log in.']);
    }
}
