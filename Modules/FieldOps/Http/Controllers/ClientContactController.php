<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Services\ClientContactInvitationService;
use Modules\FieldOps\Services\FieldOpsTenantService;

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

    // CLA-554: listing is deliberately gated by assertCanManageContacts() — who
    // else has access and what they can do is management information, not
    // general infrastructure read access (a plain can_view=true contact stays
    // unable to see this). Includes inactive (revoked) contacts on purpose, so
    // a manager can find and reactivate one without going through backoffice.
    public function index(Request $request, FoClient $foClient, FieldOpsTenantService $tenants): JsonResponse
    {
        $tenants->assertCanManageContacts($foClient, $request->user());

        $contacts = $foClient->users()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $contacts->map(fn (User $user): array => self::contactPayload($user))->values(),
        ]);
    }

    public function update(
        Request $request,
        FoClient $foClient,
        User $user,
        ClientContactInvitationService $invitations,
    ): JsonResponse {
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'can_view' => ['sometimes', 'boolean'],
            'can_report' => ['sometimes', 'boolean'],
            'can_manage_contacts' => ['sometimes', 'boolean'],
        ]);

        $invitations->updateMembership($foClient, $request->user(), $user, $data);

        $membership = $foClient->users()->whereKey($user->id)->first();

        return response()->json([
            'success' => true,
            'data' => self::contactPayload($membership),
        ]);
    }

    private static function contactPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->pivot->is_active,
            'can_view' => (bool) $user->pivot->can_view,
            'can_report' => (bool) $user->pivot->can_report,
            'can_manage_contacts' => (bool) $user->pivot->can_manage_contacts,
            'activation_required' => ! $user->hasCompletedPasswordSetup(),
        ];
    }
}
