<?php

namespace Modules\Core\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Exception;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Modules\Core\Models\User;
use Modules\Core\Services\Auth\AzureRoleService;
use Modules\Core\Services\Auth\FrontendRedirectService;

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
            // config()'s 2nd arg only applies when the key is absent — 'public_redirect' always
            // exists (mapped from MICROSOFT_AUTH_PUBLIC_REDIRECT), so an unset env var resolves
            // to null here rather than falling back. Coalesce on the resolved value instead.
            return config('services.azure.public_redirect') ?? config('services.azure.redirect');
        }

        return config('services.azure.redirect');
    }

    /**
     * Redirect the user to the Microsoft authentication page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->has('source')) {
            session(['auth_source' => $request->get('source')]);
        }

        if ($request->has('custom_redirect_url')) {
            $redirect = app(FrontendRedirectService::class)->resolve($request->string('custom_redirect_url')->toString());
            $redirect ? session(['custom_redirect_url' => $redirect]) : session()->forget('custom_redirect_url');
        } elseif ($referer = $request->headers->get('referer')) {
            $redirect = app(FrontendRedirectService::class)->resolve($referer);
            $redirect ? session(['custom_redirect_url' => $redirect]) : session()->forget('custom_redirect_url');
        }

        return Socialite::driver('azure')
            ->redirectUrl($this->oauthRedirectUri($request))
            ->scopes(['openid', 'profile', 'email', 'offline_access', 'User.Read', 'GroupMember.Read.All'])
            ->redirect();
    }

    /**
     * Obtain the user information from Microsoft.
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
            if (! $user) {
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

            // Suspended accounts are blocked regardless of flow.
            if (! $user->is_active) {
                Auth::logout();

                return redirect('/login')->withErrors(['email' => 'This account has been deactivated.']);
            }

            Auth::login($user);
            $request->session()->regenerate();

            $source = session()->pull('auth_source', 'frontend');

            // 1. Filament (web session) — intercept accounts pending setup.
            if ($source === 'filament') {
                if (! $user->hasCompletedPasswordSetup()) {
                    return redirect()->route('auth.setup-password');
                }

                return redirect()->intended(Filament::getUrl());
            }

            // 2. PWA / Frontend — resolve redirect URL first.
            $redirects = app(FrontendRedirectService::class);
            $frontendUrl = $redirects->resolve(session()->pull('custom_redirect_url')) ?? $redirects->fallback();

            // Accounts pending setup: issue a one-time activation code (never a bearer token in URL).
            if (! $user->hasCompletedPasswordSetup()) {
                $user->tokens()->where('name', 'password-setup')->delete();

                $code = Str::random(64);
                $user->forceFill([
                    'activation_code_hash' => hash('sha256', $code),
                    'activation_code_expires_at' => now()->addMinutes(10),
                ])->saveQuietly();

                return redirect()->to("{$frontendUrl}?activation_code={$code}&setup_required=true");
            }

            // Fully activated accounts: keep the session cookie flow only.
            return redirect()->to($frontendUrl);
        } catch (Exception $e) {
            report($e);

            return redirect('/login')
                ->withErrors(['microsoft' => 'Inloggen via Microsoft is mislukt. Probeer het opnieuw of neem contact op met de beheerder.']);
        }
    }
}
