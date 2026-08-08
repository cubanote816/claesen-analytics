<?php

namespace Modules\Core\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Core\Services\AccessAnalyticsService;
use Modules\Core\Models\User;

class AuthController extends Controller
{
    private const LOGIN_ATTEMPT_LIMIT = 5;
    private const LOGIN_LOCKOUT_SECONDS = 300;

    // CLA-347: email|ip alone lets an attacker rotating IPs brute-force one
    // account without ever tripping the per-IP limit. This second limiter is
    // keyed on email only, with a higher ceiling so shared/corporate IPs with
    // several legitimate users aren't penalised by the per-IP limit above.
    private const EMAIL_LOGIN_ATTEMPT_LIMIT = 20;
    private const EMAIL_LOGIN_LOCKOUT_SECONDS = 3600;

    /**
     * Session-based login for browser-first SPAs (Safety PWA, Sport, etc.).
     * Establishes an HttpOnly cookie session — never returns a token.
     */
    public function loginSpa(Request $request, AccessAnalyticsService $analytics)
    {
        $user = $this->attemptSessionLogin($request, $analytics, 'spa');

        return response()->json(['user' => $this->sessionUserPayload($user)]);
    }

    /**
     * Session-based login for the Client Portal — same contract as loginSpa(),
     * except only users with the 'client' role may establish a session here.
     * CLA-344: the Client Portal must be usable only by active client-role users.
     */
    public function loginClientPortal(Request $request, AccessAnalyticsService $analytics)
    {
        $user = $this->attemptSessionLogin($request, $analytics, 'client_portal', ['client']);

        return response()->json(['user' => $this->sessionUserPayload($user)]);
    }

    /**
     * Session-based login for Sport ("Servicios") — same contract as loginSpa(),
     * except only technician/project_manager/super_admin/admin may establish a
     * session here. CLA-363: closes the same login/spa role gap CLA-344 closed
     * for Client Portal.
     */
    public function loginSport(Request $request, AccessAnalyticsService $analytics)
    {
        $user = $this->attemptSessionLogin($request, $analytics, 'sport', ['technician', 'project_manager', 'super_admin', 'admin']);

        return response()->json(['user' => $this->sessionUserPayload($user)]);
    }

    /**
     * Shared validation/authentication pipeline for session-cookie logins.
     * When $requiredRoles is set, a user who fails that role check gets the
     * same generic auth.failed message as any other rejection — never a hint
     * that their credentials were otherwise correct.
     */
    private function attemptSessionLogin(
        Request $request,
        AccessAnalyticsService $analytics,
        string $appSourceFallback,
        ?array $requiredRoles = null,
    ): User {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $appSource = $this->resolveAppSource($request, $appSourceFallback);
        $throttleKey = $this->throttleKey($request);
        $emailThrottleKey = $this->emailThrottleKey($request);

        if ($this->isRateLimited($throttleKey) || $this->isEmailRateLimited($emailThrottleKey)) {
            $limitedKey = $this->isRateLimited($throttleKey) ? $throttleKey : $emailThrottleKey;

            $analytics->recordThrottledLogin(
                null,
                $request->input('email'),
                $appSource,
                'session_cookie',
                $request,
                'rate_limited',
                ['retry_after_seconds' => RateLimiter::availableIn($limitedKey)]
            );

            throw $this->throttleException($limitedKey);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            $analytics->recordFailedLogin(
                null,
                $request->input('email'),
                $appSource,
                'session_cookie',
                $request,
                'unknown_user'
            );
            $this->hitRateLimiter($throttleKey);
            $this->hitEmailRateLimiter($emailThrottleKey);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->is_active) {
            $analytics->recordBlockedLogin(
                $user,
                $request->input('email'),
                $appSource,
                'session_cookie',
                $request,
                'inactive_account'
            );

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->hasCompletedPasswordSetup()) {
            $analytics->recordBlockedLogin(
                $user,
                $request->input('email'),
                $appSource,
                'session_cookie',
                $request,
                'password_setup_required'
            );

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! Hash::check($request->password, $user->password)) {
            $analytics->recordFailedLogin(
                $user,
                $request->input('email'),
                $appSource,
                'session_cookie',
                $request,
                'invalid_password'
            );
            $this->hitRateLimiter($throttleKey);
            $this->hitEmailRateLimiter($emailThrottleKey);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($requiredRoles !== null && ! $user->hasAnyRole($requiredRoles)) {
            $analytics->recordBlockedLogin(
                $user,
                $request->input('email'),
                $appSource,
                'session_cookie',
                $request,
                'role_not_permitted'
            );

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        Auth::login($user);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($emailThrottleKey);

        $analytics->recordLogin($user, $appSource, 'session_cookie', $request);

        return $user;
    }

    /**
     * @return array{id: int, name: string, email: string, roles: \Illuminate\Support\Collection}
     */
    private function sessionUserPayload(User $user): array
    {
        return [
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames(),
        ];
    }

    /**
     * Canonical Login endpoint for the Core App.
     * Prepares Identity logic to absorb satellite apps.
     */
    public function login(Request $request, AccessAnalyticsService $analytics)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $appSource = $this->resolveAppSource($request, 'api');
        $throttleKey = $this->throttleKey($request);
        $emailThrottleKey = $this->emailThrottleKey($request);

        if ($this->isRateLimited($throttleKey) || $this->isEmailRateLimited($emailThrottleKey)) {
            $limitedKey = $this->isRateLimited($throttleKey) ? $throttleKey : $emailThrottleKey;

            $analytics->recordThrottledLogin(
                null,
                $request->input('email'),
                $appSource,
                'sanctum_token',
                $request,
                'rate_limited',
                ['retry_after_seconds' => RateLimiter::availableIn($limitedKey)]
            );

            throw $this->throttleException($limitedKey);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            $analytics->recordFailedLogin(
                null,
                $request->input('email'),
                $appSource,
                'sanctum_token',
                $request,
                'unknown_user'
            );
            $this->hitRateLimiter($throttleKey);
            $this->hitEmailRateLimiter($emailThrottleKey);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // Suspended, not-yet-activated, or wrong credentials → same generic error.
        if (! $user->is_active) {
            $analytics->recordBlockedLogin(
                $user,
                $request->input('email'),
                $appSource,
                'sanctum_token',
                $request,
                'inactive_account'
            );

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! $user->hasCompletedPasswordSetup()) {
            $analytics->recordBlockedLogin(
                $user,
                $request->input('email'),
                $appSource,
                'sanctum_token',
                $request,
                'password_setup_required'
            );

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if (! Hash::check($request->password, $user->password)) {
            $analytics->recordFailedLogin(
                $user,
                $request->input('email'),
                $appSource,
                'sanctum_token',
                $request,
                'invalid_password'
            );
            $this->hitRateLimiter($throttleKey);
            $this->hitEmailRateLimiter($emailThrottleKey);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $analytics->recordLogin($user, $appSource, 'sanctum_token', $request, [
            'access_token_name' => $request->input('device_name'),
        ]);

        RateLimiter::clear($throttleKey);
        RateLimiter::clear($emailThrottleKey);

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
    public function logout(Request $request, AccessAnalyticsService $analytics)
    {
        if ($request->user()) {
            $analytics->recordLogout(
                $request->user(),
                $request->user()->last_login_app_source ?? 'unknown',
                $request->user()->last_login_channel ?? 'unknown',
                $request
            );
        }

        if ($request->user() && method_exists($request->user(), 'currentAccessToken')) {
            $token = $request->user()->currentAccessToken();
            if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
                $token->delete();
            }
        }

        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    private function resolveAppSource(Request $request, string $fallback): string
    {
        return $request->string('app_source')->trim()->toString()
            ?: $request->string('source')->trim()->toString()
            ?: $request->string('device_name')->trim()->toString()
            ?: $fallback;
    }

    private function throttleKey(Request $request): string
    {
        $email = $request->string('email')->trim()->lower()->toString();
        $ip = $request->ip() ?? 'unknown';

        return "core-login:{$email}|{$ip}";
    }

    private function emailThrottleKey(Request $request): string
    {
        $email = $request->string('email')->trim()->lower()->toString();

        return "core-login-email:{$email}";
    }

    private function isRateLimited(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, self::LOGIN_ATTEMPT_LIMIT);
    }

    private function isEmailRateLimited(string $key): bool
    {
        return RateLimiter::tooManyAttempts($key, self::EMAIL_LOGIN_ATTEMPT_LIMIT);
    }

    private function hitRateLimiter(string $key): void
    {
        RateLimiter::hit($key, self::LOGIN_LOCKOUT_SECONDS);
    }

    private function hitEmailRateLimiter(string $key): void
    {
        RateLimiter::hit($key, self::EMAIL_LOGIN_LOCKOUT_SECONDS);
    }

    private function throttleException(string $key): ValidationException
    {
        $seconds = RateLimiter::availableIn($key);

        return ValidationException::withMessages([
            'email' => [__('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) max(1, ceil($seconds / 60)),
            ])],
        ])->status(429);
    }
}
