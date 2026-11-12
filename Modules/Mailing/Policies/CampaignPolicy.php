<?php

namespace Modules\Mailing\Policies;

use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Models\Campaign;

class CampaignPolicy
{
    // Gate::before in AppServiceProvider already grants super_admin full access.

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'campaign_manager', 'marketer']);
    }

    /**
     * Edit a campaign — only allowed while it is still in draft.
     * Marketers may only edit campaigns they created themselves.
     */
    public function update(User $user, Campaign $campaign): bool
    {
        if ($campaign->status !== CampaignStatus::DRAFT) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'campaign_manager'])) {
            return true;
        }

        if ($user->hasRole('marketer')) {
            return (int) $campaign->created_by === $user->id;
        }

        return false;
    }

    /**
     * Submit a draft campaign for review.
     */
    public function submit(User $user, Campaign $campaign): bool
    {
        return $campaign->status === CampaignStatus::DRAFT
            && $user->hasAnyRole(['admin', 'campaign_manager', 'marketer']);
    }

    /**
     * Approve a campaign in draft or review — marketers are explicitly excluded.
     * Approvers may skip the review step (CLA-670); marketers still submit for review.
     */
    public function approve(User $user, Campaign $campaign): bool
    {
        return in_array($campaign->status, [CampaignStatus::DRAFT, CampaignStatus::REVIEW], true)
            && $user->hasAnyRole(['admin', 'campaign_manager']);
    }

    /**
     * Send an approved campaign without a send date right now.
     */
    public function sendNow(User $user, Campaign $campaign): bool
    {
        return $campaign->canBeSentNow()
            && $user->hasAnyRole(['admin', 'campaign_manager']);
    }

    /**
     * Cancel a campaign — only admins, never on terminal states.
     */
    public function cancel(User $user, Campaign $campaign): bool
    {
        return ! $campaign->status->isTerminal()
            && $user->hasRole('admin');
    }

    /**
     * Manage the global suppression list.
     */
    public function manageSuppressions(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
