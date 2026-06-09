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

        if ($request->has('custom_redirect_url')) {
            session(['custom_redirect_url' => $request->get('custom_redirect_url')]);
        } elseif ($referer = $request->headers->get('referer')) {
            $urlParts = parse_url($referer);
            if (isset($urlParts['scheme'], $urlParts['host'])) {
                $port = isset($urlParts['port']) ? ':' . $urlParts['port'] : '';
                $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'] . $port . ($urlParts['path'] ?? '/');
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
                    ->withErrors(['microsoft' => "Toegang Geweigerd: Uw Microsoft-account ({$azureUser->getEmail()}) is niet geautoriseerd voor deze applicatie. Neem contact op met de beheerder."]);
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

            // Get the redirect URL with a robust fallback system
            $frontendUrl = session()->pull('custom_redirect_url');

            if (!$frontendUrl) {
                if (env('FRONTEND_URL')) {
                    $frontendUrl = env('FRONTEND_URL');
                } elseif (str_contains($request->headers->get('referer', ''), 'hostingersite.com')) {
                    // Force Hostinger subdirectory if detected in referer but session lost
                    $frontendUrl = 'https://lightcoral-whale-907350.hostingersite.com/safety/';
                } else {
                    $frontendUrl = app()->environment('production') 
                        ? 'https://services.claesen-verlichting.be/safety/' 
                        : 'http://localhost:5173/';
                }
            }

            // Safety check: if the URL is from Hostinger but missing /safety/, add it
            if (str_contains($frontendUrl, 'hostingersite.com') && !str_contains($frontendUrl, '/safety')) {
                $frontendUrl = rtrim($frontendUrl, '/') . '/safety/';
            }

            // Ensure the URL ends with a slash before appending the token
            $frontendUrl = rtrim($frontendUrl, '/') . '/';

            return redirect()->to("{$frontendUrl}?token={$token}");
        } catch (Exception $e) {
            return redirect('/login')
                ->withErrors(['microsoft' => 'Inloggen via Microsoft is mislukt: ' . $e->getMessage()]);
        }
    }
}
