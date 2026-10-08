<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Knx\Http\Resources\NotificationPreferenceResource;
use Modules\Knx\Models\KnxEmployee;
use Modules\Knx\Models\KnxNotification;
use Modules\Knx\Models\KnxNotificationPreference;

/**
 * The signed-in person's notification preferences (KNX-54).
 *
 * Scoped to the caller's own employee row: preferences are personal, so there is no id
 * in the URL and no way to read or write somebody else's.
 */
class NotificationPreferenceController extends Controller
{
    public function show(Request $request): array
    {
        return NotificationPreferenceResource::make(
            KnxNotificationPreference::forEmployee($this->employee($request)),
        )->resolve($request);
    }

    public function update(Request $request): array
    {
        $validated = $request->validate([
            'email' => ['required', 'array'],
            'email.immediate' => ['required', 'boolean'],
            'email.digest' => ['required', Rule::in(KnxNotificationPreference::DIGESTS)],
            'email.mutedKinds' => ['sometimes', 'array'],
            'email.mutedKinds.*' => [Rule::in(KnxNotification::KINDS)],
        ]);

        $preference = KnxNotificationPreference::forEmployee($this->employee($request));

        $preference->fill([
            'email_immediate' => $validated['email']['immediate'],
            'email_digest' => $validated['email']['digest'],
            'muted_kinds' => $validated['email']['mutedKinds'] ?? [],
        ])->save();

        return NotificationPreferenceResource::make($preference)->resolve($request);
    }

    private function employee(Request $request): KnxEmployee
    {
        /** @var KnxEmployee $employee */
        $employee = $request->attributes->get('knx_employee');

        return $employee;
    }
}
