<?php

declare(strict_types=1);

namespace Modules\Knx\Http\Resources;

use Illuminate\Http\Request;
use Modules\Knx\Models\KnxNotificationPreference;

/**
 * `NotificationPreferences` of the contract (KNX-54).
 *
 * `immediate` only governs the three critical kinds (`issue_reported`,
 * `malfunction_reported`, `visit_closed` final); the rest always travels in the digest,
 * and `off` turns the digest off. `mutedKinds` silences the app inbox, never the
 * immediate mail.
 *
 * @property-read KnxNotificationPreference $resource
 */
class NotificationPreferenceResource extends KnxResource
{
    public function toArray(Request $request): array
    {
        return [
            'email' => [
                'immediate' => (bool) $this->resource->email_immediate,
                'digest' => $this->resource->email_digest,
                'mutedKinds' => $this->resource->muted_kinds ?? [],
            ],
        ];
    }
}
