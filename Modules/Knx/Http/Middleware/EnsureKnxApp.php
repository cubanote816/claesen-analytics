<?php

namespace Modules\Knx\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Knx\Services\KnxAuthService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins each group of endpoints to the app it belongs to.
 *
 * The two KNX apps share a tenant, a token machinery and a role table — and that is
 * exactly why they need separating: `auth:sanctum` alone says "somebody signed in",
 * not *who for what*. Without this, a technician's phone token could read the whole
 * office API (clients, projects, conflicts, documents), because the tenant middleware
 * is a no-op while `organizations.enforce` is off.
 *
 * Usage: `->middleware(EnsureKnxApp::class.':office')` / `':field'`.
 *
 * It answers **401** rather than 403 on purpose: the caller is authenticated but has
 * no identity in this app at all, which is the same situation as an expired token,
 * and it is what the clients already handle by clearing the token.
 */
class EnsureKnxApp
{
    public const OFFICE = 'office';

    public const FIELD = 'field';

    /**
     * For the reads both apps share — today that is `GET /zones`, whose payload the
     * Veld contract says is Kantoor's serialiser verbatim. Writes stay scoped: a
     * technician does not update readiness checks from the field (yet).
     */
    public const ANY = 'any';

    public function __construct(private readonly KnxAuthService $auth) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $app): Response
    {
        $user = $request->user();

        $employee = match ($app) {
            self::OFFICE => $user === null ? null : $this->auth->authorizeOffice($user),
            self::FIELD => $user === null ? null : $this->auth->authorizeField($user),
            self::ANY => $user === null ? null : $this->auth->authorizeAny($user),
            default => throw new \InvalidArgumentException("Unknown KNX app [{$app}]."),
        };

        abort_if($employee === null, 401);

        // Whoever resolved it once does not have to resolve it again: the controllers
        // read the person from here instead of re-querying.
        $request->attributes->set('knx_employee', $employee);

        return $next($request);
    }
}
