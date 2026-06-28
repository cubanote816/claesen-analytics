<?php

namespace Modules\Core\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'auth_provider' => $user->microsoft_id !== null ? 'microsoft' : 'local',
            'roles'         => $user->getRoleNames(),
            'permissions'   => $user->getAllPermissions()->pluck('name'),
        ]);
    }
}
