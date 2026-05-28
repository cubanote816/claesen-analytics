<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;

class Campaign extends Model
{
    use HasFactory;

    protected $table = 'mailing_campaigns';

    protected $fillable = [
        'created_by',
        'template_name',
        'description',
        'subject_snapshot',
        'body_snapshot',
        'total_count',
        'sent_count',
        'failed_count',
        'skipped_count',
        'status',
        'approved_by',
        'approved_at',
        'finished_at',
    ];

    protected $casts = [
        'status'      => CampaignStatus::class,
        'approved_at' => 'datetime',
        'finished_at' => 'datetime',
        'total_count'   => 'integer',
        'sent_count'    => 'integer',
        'failed_count'  => 'integer',
        'skipped_count' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    // -------------------------------------------------------------------------
    // Workflow
    // -------------------------------------------------------------------------

    public function canTransitionTo(CampaignStatus $new): bool
    {
        return in_array($new, $this->status->allowedTransitions(), true);
    }

    public function transitionTo(CampaignStatus $new, ?int $actorId = null): void
    {
        if (! $this->canTransitionTo($new)) {
            throw new \DomainException(
                "Cannot transition campaign #{$this->id} from '{$this->status->value}' to '{$new->value}'."
            );
        }

        $attributes = ['status' => $new];

        if ($new === CampaignStatus::APPROVED) {
            $attributes['approved_by'] = $actorId ?? auth()->id();
            $attributes['approved_at'] = now();
        }

        $this->update($attributes);
    }

    public function canBeApprovedBy(User $user): bool
    {
        return $this->status === CampaignStatus::REVIEW
            && $user->hasAnyRole(['super_admin', 'admin', 'campaign_manager']);
    }
}
