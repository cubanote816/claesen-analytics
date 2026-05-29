<?php

namespace Modules\Mailing\Services;

use Modules\Mailing\Enums\SuppressionReason;
use Modules\Mailing\Models\SuppressionEntry;

class SuppressionService
{
    public function isSuppressed(string $email): bool
    {
        return SuppressionEntry::where('email', strtolower(trim($email)))->exists();
    }

    public function getReason(string $email): ?SuppressionReason
    {
        return SuppressionEntry::where('email', strtolower(trim($email)))
            ->value('reason');
    }

    public function suppress(
        string $email,
        SuppressionReason $reason,
        ?int $prospectId = null,
        ?int $campaignId = null,
        ?int $suppressedBy = null,
        ?string $notes = null,
    ): void {
        $email = strtolower(trim($email));

        $existing = SuppressionEntry::where('email', $email)->first();

        if ($existing && $existing->reason->isPermanent() && ! $reason->isPermanent()) {
            throw new \DomainException(
                "Cannot downgrade suppression for {$email}: current reason '{$existing->reason->value}' is permanent."
            );
        }

        SuppressionEntry::updateOrCreate(
            ['email' => $email],
            [
                'prospect_id'       => $prospectId,
                'reason'            => $reason,
                'source_campaign_id' => $campaignId,
                'notes'             => $notes,
                'suppressed_at'     => now(),
                'suppressed_by'     => $suppressedBy,
            ]
        );
    }
}
