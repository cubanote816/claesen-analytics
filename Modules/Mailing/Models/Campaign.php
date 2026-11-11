<?php

namespace Modules\Mailing\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Enums\FollowUpTrigger;
use Modules\Mailing\Enums\TemplateCategory;
use Modules\Mailing\Jobs\ExecuteCampaignJob;
use Modules\Mailing\Models\EmailTemplate;
use Modules\Mailing\Services\SegmentResolverService;
use Modules\Prospects\Models\Prospect;

class Campaign extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return \Modules\Mailing\Database\Factories\CampaignFactory::new();
    }

    protected $table = 'mailing_campaigns';

    protected $fillable = [
        'created_by',
        'template_id',
        'template_name',
        'description',
        'subject_snapshot',
        'body_snapshot',
        'template_category_snapshot',
        'preference_category_snapshot',
        'dispatch_key',
        'run_generation',
        'run_owner',
        'total_count',
        'sent_count',
        'failed_count',
        'skipped_count',
        'status',
        'approved_by',
        'approved_at',
        'finished_at',
        'audience_type',
        'audience_filters',
        'scheduled_at',
        'timezone',
        'ab_subject_b',
        'ab_split_percent',
        'ab_winner_after_hours',
        'ab_winner_variant',
        'ab_winner_selected_at',
        'ab_test_started_at',
        'followup_campaign_id',
        'followup_trigger',
        'followup_delay_hours',
        'followup_dispatched_at',
    ];

    protected $casts = [
        'audience_type'    => AudienceType::class,
        'status'           => CampaignStatus::class,
        'approved_at'      => 'datetime',
        'finished_at'      => 'datetime',
        'scheduled_at'     => 'datetime',
        'audience_filters' => 'array',
        'run_generation'       => 'integer',
        'total_count'          => 'integer',
        'sent_count'           => 'integer',
        'failed_count'         => 'integer',
        'skipped_count'        => 'integer',
        'ab_split_percent'     => 'integer',
        'ab_winner_after_hours' => 'integer',
        'ab_winner_selected_at'  => 'datetime',
        'ab_test_started_at'    => 'datetime',
        'followup_trigger'      => FollowUpTrigger::class,
        'followup_delay_hours'  => 'integer',
        'followup_dispatched_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }

    /** The campaign configured as follow-up child of this one. */
    public function followUpCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'followup_campaign_id');
    }

    /** The parent campaign that has this one as a follow-up (inverse). */
    public function followUpParent(): HasMany
    {
        return $this->hasMany(Campaign::class, 'followup_campaign_id');
    }

    // -------------------------------------------------------------------------
    // Snapshot builder
    // -------------------------------------------------------------------------

    /**
     * Captures all immutable fields from a template into an array suitable for Campaign::create/update.
     * Always use this method — never trust Hidden form fields for these values.
     *
     * @throws \InvalidArgumentException if the template contains an invalid preference_category.
     */
    public static function buildSnapshotFrom(EmailTemplate $template): array
    {
        $validKeys    = array_keys(config('mailing.preference_categories', []));
        $prefCategory = $template->preference_category;

        // Transactional: enforce null regardless of what the template stores
        if ($template->category === TemplateCategory::TRANSACTIONAL) {
            $prefCategory = null;
        }

        if ($prefCategory !== null && ! in_array($prefCategory, $validKeys, true)) {
            throw new \InvalidArgumentException(
                "Invalid preference_category '{$prefCategory}' on template '{$template->name}'. "
                . 'Valid: ' . implode(', ', $validKeys) . '.'
            );
        }

        return [
            'template_name'                => $template->name,
            'subject_snapshot'             => $template->subject,
            'body_snapshot'                => $template->body,
            'template_category_snapshot'   => $template->category->value,
            'preference_category_snapshot' => $prefCategory,
        ];
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
            $template = $this->template;

            if (! $template) {
                throw new \DomainException(
                    "Campaign #{$this->id}: cannot approve — no template linked."
                );
            }

            // Recompute snapshots server-side at approval — Hidden fields are not trusted
            $snapshot  = static::buildSnapshotFrom($template);
            $validKeys = array_keys(config('mailing.preference_categories', []));

            if ($snapshot['template_category_snapshot'] === TemplateCategory::COMMERCIAL->value
                && ! in_array($snapshot['preference_category_snapshot'], $validKeys, true)) {
                throw new \DomainException(
                    "Campaign #{$this->id}: commercial campaigns require a valid preference category "
                    . "before approval. Edit template '{$template->name}' to assign one."
                );
            }

            $attributes['template_category_snapshot']   = $snapshot['template_category_snapshot'];
            $attributes['preference_category_snapshot'] = $snapshot['preference_category_snapshot'];
            $attributes['approved_by'] = $actorId ?? auth()->id();
            $attributes['approved_at'] = now();
        }

        $this->update($attributes);
    }

    /**
     * Returns true when this campaign has a follow-up configured.
     * Guards against self-reference: a campaign cannot follow up itself.
     */
    public function hasFollowUp(): bool
    {
        return $this->followup_campaign_id !== null
            && $this->followup_campaign_id !== $this->id
            && $this->followup_trigger !== null
            && $this->followup_delay_hours !== null;
    }

    /** Returns true when this campaign has a variant B subject defined. */
    public function isAbTest(): bool
    {
        return ! empty($this->ab_subject_b);
    }

    public function canBeApprovedBy(User $user): bool
    {
        return in_array($this->status, [CampaignStatus::DRAFT, CampaignStatus::REVIEW], true)
            && $user->hasAnyRole(['super_admin', 'admin', 'campaign_manager']);
    }

    /**
     * True when another, non-cancelled campaign uses this one as its follow-up.
     * mailing:dispatch-followups sends such a campaign itself. followup_dispatched_at is
     * deliberately ignored: it is set on the parent when the child job is queued, while
     * the child stays approved until the worker runs, so a manual send would double it.
     */
    public function isFollowUpChild(): bool
    {
        return static::query()
            ->where('followup_campaign_id', $this->id)
            ->where('id', '!=', $this->id)
            ->where('status', '!=', CampaignStatus::CANCELLED->value)
            ->exists();
    }

    /**
     * CLA-669: the scheduler ignores campaigns with a NULL scheduled_at, so an approved
     * campaign without a send date can only go out through an explicit "Send now".
     */
    public function canBeSentNow(): bool
    {
        return $this->status === CampaignStatus::APPROVED
            && $this->scheduled_at === null
            && ! $this->isFollowUpChild();
    }

    /**
     * Atomically moves approved → sending. ExecuteCampaignJob skips its own claim when the
     * status is already sending, so callers must claim first; only the winner may dispatch.
     */
    public function claimForImmediateSend(): bool
    {
        $claimed = static::query()
            ->whereKey($this->id)
            ->where('status', CampaignStatus::APPROVED->value)
            ->whereNull('scheduled_at')
            ->update(['status' => CampaignStatus::SENDING->value]) === 1;

        if ($claimed) {
            $this->status = CampaignStatus::SENDING;
            $this->syncOriginalAttribute('status');
        }

        return $claimed;
    }

    /**
     * Claims the campaign and queues its send. If queueing throws, no job exists yet, so the
     * claim is released (sending → approved) and "Send now" stays available for a retry.
     *
     * @return bool false when another request already claimed the campaign
     *
     * @throws \Throwable when the job could not be queued; the claim is already released
     */
    public function dispatchImmediateSend(): bool
    {
        if (! $this->claimForImmediateSend()) {
            return false;
        }

        try {
            Bus::dispatch(new ExecuteCampaignJob(campaignId: $this->id));
        } catch (\Throwable $e) {
            static::query()
                ->whereKey($this->id)
                ->where('status', CampaignStatus::SENDING->value)
                ->update(['status' => CampaignStatus::APPROVED->value]);

            $this->status = CampaignStatus::APPROVED;
            $this->syncOriginalAttribute('status');

            throw $e;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Audience resolution
    // -------------------------------------------------------------------------

    /**
     * Returns the list of prospect IDs for this campaign's audience.
     *
     * - ALL_SUBSCRIBED: every subscribed, non-suppressed prospect.
     * - SEGMENT: resolved dynamically via SegmentResolverService.
     * - MANUAL: the prospect_ids stored in audience_filters, minus unsubscribed/suppressed.
     *
     * @return array<int>
     */
    public function resolveAudience(): array
    {
        return match ($this->audience_type ?? AudienceType::ALL_SUBSCRIBED) {
            AudienceType::ALL_SUBSCRIBED => Prospect::query()
                ->whereNull('unsubscribed_at')
                ->whereNotIn('id', function ($sub): void {
                    $sub->select('prospect_id')
                        ->from('mailing_suppression_list')
                        ->whereNotNull('prospect_id');
                })
                ->pluck('id')
                ->toArray(),

            // prospect_ids belongs to the manual audience only; it must never narrow a segment.
            AudienceType::SEGMENT => app(SegmentResolverService::class)
                ->resolveIds(Arr::except($this->audience_filters ?? [], ['prospect_ids'])),

            AudienceType::MANUAL => app(SegmentResolverService::class)
                ->resolveIds(['prospect_ids' => $this->audience_filters['prospect_ids'] ?? []]),
        };
    }
}
