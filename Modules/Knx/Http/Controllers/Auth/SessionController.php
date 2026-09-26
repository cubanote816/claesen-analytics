<?php

namespace Modules\Knx\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Knx\Http\Resources\SessionResource;
use Modules\Knx\Services\KantoorAuthService;

/**
 * `GET /me/session` — what the office app calls to validate its stored token on
 * start-up. A 401 here is what tells the front to clear the token.
 */
class SessionController extends Controller
{
    public function __construct(private readonly KantoorAuthService $auth) {}

    public function show(Request $request): SessionResource
    {
        $user = $request->user();
        $employee = $user === null ? null : $this->auth->authorize($user);

        // A token can outlive the person's access: a valid token whose account no
        // longer has an office person must behave exactly like a missing token.
        abort_if($employee === null, 401);

        return SessionResource::make($employee);
    }
}
