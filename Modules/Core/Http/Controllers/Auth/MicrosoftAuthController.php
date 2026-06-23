<?php

namespace Modules\Core\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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
     * Returns the OAuth redirect URI to use.
     * API requests (Safety Hub / PWA) use the public-facing URL registered in Azure.
     * Internal Filament requests use the intranet URL.
     */
    private function oauthRedirectUri(Request $request): string
    {
        if ($request->is('api/*')) {
            return config('services.azure.public_redirect', config('services.azure.redirect'));
        }

        return config('services.azure.redirect');
    }

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
            ->redirectUrl($this->oauthRedirectUri($request))
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
            $azureUser = Socialite::driver('azure')
                ->redirectUrl($this->oauthRedirectUri($request))
                ->user();

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

            // 1. Filament (web session) — intercept accounts pending setup.
            if ($source === 'filament') {
                if (! $user->hasCompletedPasswordSetup()) {
                    return redirect()->route('auth.setup-password');
                }

                return redirect()->intended(Filament::getUrl());
            }

            // 2. PWA / Frontend — resolve redirect URL first.
            $frontendUrl = session()->pull('custom_redirect_url');

            if (! $frontendUrl) {
                if (env('FRONTEND_URL')) {
                    $frontendUrl = env('FRONTEND_URL');
                } elseif (str_contains($request->headers->get('referer', ''), 'hostingersite.com')) {
                    $frontendUrl = 'https://lightcoral-whale-907350.hostingersite.com/safety/';
                } else {
                    $frontendUrl = app()->environment('production')
                        ? 'https://service.claesen-verlichting.be/'
                        : 'http://localhost:5173/';
                }
            }

            // Normalize to origin-root: the PWA service worker intercepts sub-path navigations
            // before nginx can redirect them, causing React Router to see an unmatched route.
            // Always redirect to the origin root so the SW serves index.html at the correct path.
            $parts = parse_url($frontendUrl);
            if (isset($parts['scheme'], $parts['host']) && ! str_contains($frontendUrl, 'hostingersite.com')) {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $frontendUrl = $parts['scheme'] . '://' . $parts['host'] . $port . '/';
            } else {
                if (str_contains($frontendUrl, 'hostingersite.com') && ! str_contains($frontendUrl, '/safety')) {
                    $frontendUrl = rtrim($frontendUrl, '/') . '/safety/';
                }
                $frontendUrl = rtrim($frontendUrl, '/') . '/';
            }

            // Accounts pending setup: issue a one-time activation code (never a bearer token in URL).
            if (! $user->hasCompletedPasswordSetup()) {
                $user->tokens()->where('name', 'password-setup')->delete();

                $code = Str::random(64);
                $user->forceFill([
                    'activation_code_hash'       => hash('sha256', $code),
                    'activation_code_expires_at' => now()->addMinutes(10),
                ])->saveQuietly();

                return redirect()->to("{$frontendUrl}?activation_code={$code}&setup_required=true");
            }

            // Fully activated accounts: issue the normal bearer token.
            $token = $user->createToken('auth_token')->plainTextToken;

            return redirect()->to("{$frontendUrl}?token={$token}");
        } catch (Exception $e) {
            return redirect('/login')
                ->withErrors(['microsoft' => 'Inloggen via Microsoft is mislukt: ' . $e->getMessage()]);
        }
    }
}
