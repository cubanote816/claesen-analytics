<?php

namespace Modules\Core\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Modules\Core\Models\User;
use Modules\Core\Services\Auth\AzureRoleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Filament\Facades\Filament;
use Exception;

class MicrosoftAuthController extends Controller
{
    /**
     * Redirect the user to the Microsoft authentication page.
     * 
     * @return RedirectResponse
     */
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->has('source')) {
            session(['auth_source' => $request->get('source')]);
        }

        // Capture referer to handle dynamic redirection (e.g. Hostinger, Local, Production)
        $referer = $request->headers->get('referer');
        if ($referer) {
            $urlParts = parse_url($referer);
            if (isset($urlParts['scheme'], $urlParts['host'])) {
                $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . ($urlParts['path'] ?? '/');
                session(['custom_redirect_url' => $baseUrl]);
            }
        }

        return Socialite::driver('azure')
            ->scopes(['openid', 'profile', 'email', 'offline_access', 'User.Read', 'GroupMember.Read.All'])
            ->redirect();
    }

    /**
     * Obtain the user information from Microsoft.
     * 
     * @param AzureRoleService $roleService
     * @return RedirectResponse
     */
    public function callback(Request $request, AzureRoleService $roleService): RedirectResponse
    {
        try {
            $azureUser = Socialite::driver('azure')->user();

            // Find the local user by email (only in 'mysql' connection)
            $user = User::where('email', $azureUser->getEmail())->first();

            // SECURITY: If user does not exist locally, deny access
            if (!$user) {
                return redirect('/login')
                    ->withErrors(['microsoft' => 'Toegang Geweigerd: Uw account is niet geautoriseerd para deze applicatie. Neem contact op met de beheerder.']);
            }

            // Update user with Azure details
            $user->update([
                'name' => $azureUser->getName(),
                'microsoft_id' => $azureUser->getId(),
                'azure_token' => $azureUser->token,
                'azure_refresh_token' => $azureUser->refreshToken ?? null,
                'azure_token_expires_at' => property_exists($azureUser, 'expiresIn') ? now()->addSeconds($azureUser->expiresIn) : null,
            ]);

            // Synchronize roles based on Azure Groups (if available in the token/user data)
            $groups = $azureUser->user['groups'] ?? [];
            $roleService->syncRolesFromAzure($user, $groups);

            Auth::login($user);

            $source = session()->pull('auth_source', 'frontend');

            // 1. If coming from Filament, redirect back to Admin Panel
            if ($source === 'filament') {
                return redirect()->intended(Filament::getUrl());
            }

            // 2. If coming from Frontend (PWA), generate token and redirect
            $token = $user->createToken('auth_token')->plainTextToken;

            // Priority: Session (Custom) > .env > config > dynamic fallback
            $frontendUrl = session()->pull('custom_redirect_url')
                ?? env('FRONTEND_URL')
                ?? config('app.frontend_url')
                ?? (app()->environment('production') ? 'https://services.claesen-verlichting.be/safety/' : 'http://localhost:5173/');

            // Ensure the URL ends with a slash before appending the token
            $frontendUrl = rtrim($frontendUrl, '/') . '/';

            return redirect()->to("{$frontendUrl}?token={$token}");
        } catch (Exception $e) {
            return redirect('/login')
                ->withErrors(['microsoft' => 'Inloggen via Microsoft is mislukt: ' . $e->getMessage()]);
        }
    }
}
