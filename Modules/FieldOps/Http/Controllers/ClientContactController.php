<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Services\ClientContactInvitationService;

class ClientContactController extends Controller
{
    public function invite(
        Request $request,
        FoClient $foClient,
        ClientContactInvitationService $invitations,
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'language' => ['nullable', Rule::in(['nl', 'en', 'fr', 'de'])],
            'can_view' => ['nullable', 'boolean'],
            'can_report' => ['nullable', 'boolean'],
            'can_manage_contacts' => ['nullable', 'boolean'],
        ]);
        $user = $invitations->invite($foClient, $request->user(), $data);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'activation_required' => ! $user->hasCompletedPasswordSetup(),
            ],
        ], 201);
    }
}
