<?php

namespace Modules\Knx\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Models\User;
use Modules\Knx\Http\Requests\LoginRequest;
use Modules\Knx\Http\Resources\SessionResource;
use Modules\Knx\Services\KantoorAuthService;

/**
 * Session endpoints of docs/BACKEND-API.md §7.
 *
 * Deviations from that section, both deliberate and documented in
 * docs/Knx/knx-kantoor-backend.md:
 *
 *   - there is no separate refresh token: `POST /auth/refresh` accepts either a
 *     `refresh_token` in the body (the documented shape) or the access token in
 *     the Authorization header, and rotates whichever it was given. The front
 *     only ever stores the access token, so a second credential would have been
 *     unused machinery;
 *   - `expires_in` is the configured lifetime in seconds (§7 recommends 15-60
 *     minutes; the default here is 60).
 */
class AuthController extends Controller
{
    public function __construct(private readonly KantoorAuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->attempt(
            (string) $request->string('email'),
            (string) $request->string('password'),
        );

        if ($result === null) {
            $this->auth->fail();
        }

        [$user, $employee] = $result;

        return response()->json([
            'access_token' => $this->issueToken($user),
            'expires_in' => $this->expiresInSeconds(),
            'user' => SessionResource::make($employee)->resolve(),
        ]);
    }

    /**
     * Rotates the credential the caller presented. The old token is deleted in
     * the same request, so a leaked token cannot be used twice.
     */
    public function refresh(Request $request): JsonResponse
    {
        $token = $this->presentedToken($request);

        if (
            ! $token instanceof PersonalAccessToken
            || ($token->expires_at !== null && $token->expires_at->isPast())
        ) {
            throw ValidationException::withMessages([
                'refresh_token' => __('knx::auth.refresh_invalid'),
            ]);
        }

        $user = $token->tokenable;

        // The account is re-checked here, not just at login: someone deactivated
        // (or stripped of the role) since signing in must not be able to mint a
        // fresh token.
        if (
            ! $user instanceof User
            || $this->auth->authorize($user) === null
        ) {
            $token->delete();

            throw ValidationException::withMessages([
                'refresh_token' => __('knx::auth.refresh_invalid'),
            ]);
        }

        $token->delete();

        return response()->json([
            'access_token' => $this->issueToken($user),
            'expires_in' => $this->expiresInSeconds(),
        ]);
    }

    /**
     * Revokes the token the caller presented. Deliberately resolved from the
     * request rather than via `currentAccessToken()`: on a stateful request that
     * returns a transient token with nothing in the database to delete, and a
     * logout that silently keeps the token alive is worse than no logout at all.
     */
    public function logout(Request $request): Response
    {
        $this->presentedToken($request)?->delete();

        return response()->noContent();
    }

    /**
     * The credential the caller is rotating: the documented `refresh_token` body
     * field, or the access token itself in the Authorization header. The route is
     * deliberately NOT behind auth:sanctum so that a client holding only the body
     * credential can still refresh, which is what §7 describes.
     */
    private function presentedToken(Request $request): ?PersonalAccessToken
    {
        $presented = $request->input('refresh_token') ?? $request->bearerToken();

        return $presented === null ? null : PersonalAccessToken::findToken((string) $presented);
    }

    private function issueToken(User $user): string
    {
        return $user->createToken(
            'knx:kantoor',
            ['knx:office'],
            now()->addMinutes((int) config('knx.token_expiry_minutes')),
        )->plainTextToken;
    }

    private function expiresInSeconds(): int
    {
        return (int) config('knx.token_expiry_minutes') * 60;
    }
}
