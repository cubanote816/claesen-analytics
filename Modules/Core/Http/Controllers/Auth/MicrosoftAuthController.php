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
use Modules\Core\Http\Middleware\ResolveSessionCookieDomain;
use Modules\Core\Models\User;
use Modules\Core\Services\Auth\AzureRoleService;
use Modules\Core\Services\Auth\FrontendRedirectService;
use Modules\Core\Services\AccessAnalyticsService;

class MicrosoftAuthController extends Controller
{
    /**
     * Returns the OAuth redirect URI to use.
     * API requests (Safety Hub / Sport / PWA) use the public-facing URL registered in Azure
     * for whichever frontend initiated the flow — see redirect() below, which derives and
     * caches it in session since a single static MICROSOFT_AUTH_PUBLIC_REDIRECT can only
     * ever serve one frontend's registered Azure callback.
     * Internal Filament requests use the intranet URL.
     */
    private function oauthRedirectUri(Request $request): string
    {
        if ($request->is('api/*')) {
            // config()'s 2nd arg only applies when the key is absent — 'public_redirect' always
            // exists (mapped from MICROSOFT_AUTH_PUBLIC_REDIRECT), so an unset env var resolves
            // to null here rather than falling back. Coalesce on the resolved value instead.
            return session('oauth_redirect_uri')
                ?? config('services.azure.public_redirect')
                ?? config('services.azure.redirect');
        }

        return config('services.azure.redirect');
    }

    /**
     * Redirect the user to the Microsoft authentication page.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $source = $request->input('source', $request->input('app_source'));

        if ($source) {
            session(['auth_source' => $source]);
        }

        $redirect = null;

        if ($request->has('custom_redirect_url')) {
            $redirect = app(FrontendRedirectService::class)->resolve($request->string('custom_redirect_url')->toString());
        } elseif ($referer = $request->headers->get('referer')) {
            $redirect = app(FrontendRedirectService::class)->resolve($referer);
        }

        $redirect ? session(['custom_redirect_url' => $redirect]) : session()->forget('custom_redirect_url');

        // CLA-XXX: cache the callback URI for *this* frontend's origin so callback() sends
        // Microsoft back to the same domain the flow started on — required for the session
        // cookie set here to still be readable there, and for the app's own Azure-registered
        // redirect URI (safety.claesen-verlichting.be, service.claesen-verlichting.be, ...)
        // to actually match what gets sent.
        if ($request->is('api/*')) {
            $origin = null;
            $originHost = null;

            if ($redirect) {
                $parts = parse_url($redirect);

                if (isset($parts['scheme'], $parts['host'])) {
                    $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
                    $originHost = $parts['host'];
                }
            }

            $origin
                ? session(['oauth_redirect_uri' => $origin.'/api/v1/auth/microsoft/callback'])
                : session()->forget('oauth_redirect_uri');

            // CLA-XXX: the callback request is reached via a redirect FROM the IdP, so its
            // Referer points at login.microsoftonline.com, not at us — ResolveSessionCookieDomain
            // can't trust Origin/Referer there. Stash the domain decision in a plain cookie now,
            // while Referer is still reliable (this request came straight from the frontend), so
            // the middleware can fall back to it on the callback. See that class for the full story.
            cookie()->queue(cookie(
                name: ResolveSessionCookieDomain::OAUTH_HINT_COOKIE,
                value: ResolveSessionCookieDomain::isProductionHost($originHost) ? '1' : '0',
                minutes: 10,
                path: '/',
                secure: true,
                httpOnly: true,
                sameSite: 'lax',
            ));
        }

        return Socialite::driver('azure')
            ->redirectUrl($this->oauthRedirectUri($request))
            ->scopes(['openid', 'profile', 'email', 'offline_access', 'User.Read', 'GroupMember.Read.All'])
            ->redirect();
    }

    /**
     * Obtain the user information from Microsoft.
     */
    public function callback(Request $request, AzureRoleService $roleService, AccessAnalyticsService $analytics): RedirectResponse
    {
        try {
            $azureUser = Socialite::driver('azure')
                ->redirectUrl($this->oauthRedirectUri($request))
                ->user();

            // Find the local user by email (only in 'mysql' connection)
            $user = User::where('email', $azureUser->getEmail())->first();

            // SECURITY: If user does not exist locally, deny access
            if (! $user) {
                $analytics->recordFailedLogin(
                    null,
                    $azureUser->getEmail(),
                    'backoffice',
                    'azure_oauth_session',
                    $request,
                    'account_not_authorized',
                    ['provider' => 'azure']
                );

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
                $analytics->recordBlockedLogin(
                    $user,
                    $azureUser->getEmail(),
                    'backoffice',
                    'azure_oauth_session',
                    $request,
                    'inactive_account',
                    ['provider' => 'azure']
                );

                Auth::logout();

                return redirect('/login')->withErrors(['email' => 'This account has been deactivated.']);
            }

            // CLA-344/CLA-363: resolve the intended destination (and, for the backoffice,
            // peek whether this is a Filament login) before establishing the session, so a
            // non-permitted user is blocked before ever getting a session cookie — for any
            // of the 4 destinations, not just Client Portal. Peeking here (session(), not
            // pull) does not consume the values — the existing pulls further down still run
            // normally and resolve to the same values.
            $redirects = app(FrontendRedirectService::class);
            $intendedFrontendUrl = $redirects->resolve(session('custom_redirect_url')) ?? $redirects->fallback();
            $intendedSource = session('auth_source', 'frontend');

            if ($intendedSource === 'filament') {
                // Deny-list client/technician; every other existing role
                // (financial_manager, hr_manager, viewer, project_manager,
                // super_admin, admin, ...) is left untouched here — hasPanelAccess()/
                // EnsurePanelAccess still governs which resources they see once inside.
                if ($user->hasRole('client') || $user->hasRole('technician')) {
                    $analytics->recordBlockedLogin(
                        $user,
                        $azureUser->getEmail(),
                        'backoffice',
                        'azure_oauth_session',
                        $request,
                        'role_not_permitted',
                        ['provider' => 'azure']
                    );

                    Auth::logout();

                    return redirect('/login')
                        ->withErrors(['microsoft' => "Toegang Geweigerd: Uw Microsoft-account ({$azureUser->getEmail()}) is niet geautoriseerd voor deze applicatie. Neem contact op met de beheerder."]);
                }
            } else {
                $roleGate = $this->roleGateForFrontend($redirects, $intendedFrontendUrl);

                if ($roleGate !== null && ! $user->hasAnyRole($roleGate['roles'])) {
                    $analytics->recordBlockedLogin(
                        $user,
                        $azureUser->getEmail(),
                        $roleGate['appSource'],
                        'azure_oauth_session',
                        $request,
                        'role_not_permitted',
                        ['provider' => 'azure']
                    );

                    Auth::logout();

                    return redirect('/login')
                        ->withErrors(['microsoft' => "Toegang Geweigerd: Uw Microsoft-account ({$azureUser->getEmail()}) is niet geautoriseerd voor deze applicatie. Neem contact op met de beheerder."]);
                }
            }

            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            $source = session()->pull('auth_source', 'frontend');

            // 1. Filament (web session) — intercept accounts pending setup.
            if ($source === 'filament') {
                $analytics->recordLogin($user, 'backoffice', 'azure_oauth_session', $request, [
                    'redirect' => 'filament',
                ]);

                if (! $user->hasCompletedPasswordSetup()) {
                    return redirect()->route('auth.setup-password');
                }

                return redirect()->intended(Filament::getUrl());
            }

            // 2. PWA / Frontend — resolve redirect URL first.
            $redirects = app(FrontendRedirectService::class);
            $frontendUrl = $redirects->resolve(session()->pull('custom_redirect_url')) ?? $redirects->fallback();

            $appSource = $this->deriveAppSource($source, $frontendUrl);

            // Accounts pending setup: issue a one-time activation code (never a bearer token in URL).
            if (! $user->hasCompletedPasswordSetup()) {
                $analytics->recordLogin($user, $appSource, 'azure_oauth_session', $request, [
                    'redirect_url' => $frontendUrl,
                    'setup_required' => true,
                ]);

                $user->tokens()->where('name', 'password-setup')->delete();

                $code = Str::random(64);
                $user->forceFill([
                    'activation_code_hash' => hash('sha256', $code),
                    'activation_code_expires_at' => now()->addMinutes(10),
                ])->saveQuietly();

                return redirect()->to("{$frontendUrl}?activation_code={$code}&setup_required=true");
            }

            $analytics->recordLogin($user, $appSource, 'azure_oauth_session', $request, [
                'redirect_url' => $frontendUrl,
            ]);

            // Fully activated accounts: keep the session cookie flow only.
            return redirect()->to($frontendUrl);
        } catch (Exception $e) {
            report($e);
            $analytics->recordFailedLogin(
                null,
                $request->input('email'),
                $this->deriveAppSource((string) ($request->input('source', $request->input('app_source', 'frontend'))), null),
                'azure_oauth_session',
                $request,
                'oauth_exception',
                ['exception_class' => class_basename($e)]
            );

            return redirect('/login')
                ->withErrors(['microsoft' => 'Inloggen via Microsoft is mislukt. Probeer het opnieuw of neem contact op met de beheerder.']);
        }
    }

    /**
     * CLA-363: maps an intended frontend destination to the roles allowed to log
     * into it, mirroring AuthController::loginSport()/loginClientPortal() and
     * Safety's own Modules\Safety\Http\Controllers\AuthController::login() — same
     * role list, different codebase. Returns null for destinations with no
     * dedicated role gate.
     *
     * @return array{roles: array<int, string>, appSource: string}|null
     */
    private function roleGateForFrontend(FrontendRedirectService $redirects, ?string $intendedFrontendUrl): ?array
    {
        if ($redirects->sameOrigin($intendedFrontendUrl, config('fieldops.client_portal_url'))) {
            return ['roles' => ['client'], 'appSource' => 'client_portal'];
        }

        if ($redirects->sameOrigin($intendedFrontendUrl, config('fieldops.safety_app_url'))) {
            return ['roles' => ['project_manager', 'super_admin', 'admin'], 'appSource' => 'safety'];
        }

        if ($redirects->sameOrigin($intendedFrontendUrl, config('fieldops.field_app_url'))) {
            return ['roles' => ['technician', 'project_manager', 'super_admin', 'admin'], 'appSource' => 'sport'];
        }

        return null;
    }

    private function deriveAppSource(string $source, ?string $frontendUrl): string
    {
        if ($source !== '' && $source !== 'frontend') {
            return $source;
        }

        if (! $frontendUrl) {
            return 'frontend';
        }

        $path = parse_url($frontendUrl, PHP_URL_PATH) ?: '';

        foreach (['safety', 'sport', 'fieldops'] as $candidate) {
            if (str_contains($path, $candidate)) {
                return $candidate;
            }
        }

        return 'frontend';
    }
}
