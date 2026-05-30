<?php

namespace Modules\Mailing\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Modules\Prospects\Models\Prospect;

/**
 * Resolves a mailing audience from an audience_filters JSON structure.
 *
 * Safety invariants (always applied regardless of rule logic):
 *  - Only prospects with unsubscribed_at IS NULL are included.
 *  - Suppressed prospects (prospect_id in mailing_suppression_list) are excluded.
 *
 * These invariants are applied OUTSIDE the rule group closure so that an OR rule
 * cannot accidentally include a suppressed or unsubscribed prospect.
 */
class SegmentResolverService
{
    /**
     * Fields allowed in prospect_field rules. Arbitrary fields must never reach where().
     */
    private const ALLOWED_FIELDS = ['language', 'federation', 'region_id'];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function query(array $filters, bool $excludeSuppressed = true): Builder
    {
        $query = Prospect::query()
            ->select('prospects_prospects.*')
            ->whereNull('prospects_prospects.unsubscribed_at');

        if ($excludeSuppressed) {
            $this->applySuppression($query);
        }

        $rules = $filters['rules'] ?? [];
        if (! empty($rules)) {
            $query->where(function (Builder $ruleQuery) use ($filters): void {
                $this->applyRuleGroup($ruleQuery, $filters);
            });
        }

        return $query;
    }

    public function count(array $filters, bool $excludeSuppressed = true): int
    {
        return $this->query($filters, $excludeSuppressed)->count();
    }

    /** @return array<int> */
    public function resolveIds(array $filters, bool $excludeSuppressed = true): array
    {
        return $this->query($filters, $excludeSuppressed)
            ->pluck('prospects_prospects.id')
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Invariant constraints (always AND at the outer query level)
    // -------------------------------------------------------------------------

    private function applySuppression(Builder $query): void
    {
        $query->whereNotIn('prospects_prospects.id', function ($sub): void {
            $sub->select('prospect_id')
                ->from('mailing_suppression_list')
                ->whereNotNull('prospect_id');
        });
    }

    // -------------------------------------------------------------------------
    // Rule group
    // -------------------------------------------------------------------------

    private function applyRuleGroup(Builder $ruleQuery, array $filters): void
    {
        $operator = strtoupper($filters['operator'] ?? 'AND');
        $rules    = $filters['rules'] ?? [];

        foreach ($rules as $rule) {
            $addMethod = $operator === 'OR' ? 'orWhere' : 'where';

            $ruleQuery->{$addMethod}(function (Builder $sub) use ($rule): void {
                $this->applyRule($sub, $rule);
            });
        }
    }

    private function applyRule(Builder $query, array $rule): void
    {
        match ($rule['type'] ?? '') {
            'has_event'      => $this->applyHasEvent($query, $rule, exists: true),
            'has_no_event'   => $this->applyHasEvent($query, $rule, exists: false),
            'prospect_field' => $this->applyProspectField($query, $rule),
            default          => Log::warning('SegmentResolverService: unknown rule type', ['rule' => $rule]),
        };
    }

    // -------------------------------------------------------------------------
    // Rule implementations
    // -------------------------------------------------------------------------

    private function applyHasEvent(Builder $query, array $rule, bool $exists): void
    {
        $method = $exists ? 'whereExists' : 'whereNotExists';

        $query->{$method}(function ($sub) use ($rule): void {
            $sub->selectRaw('1')
                ->from('mailing_messages')
                ->join('mailing_message_events', 'mailing_message_events.message_id', '=', 'mailing_messages.id')
                ->whereColumn('mailing_messages.prospect_id', 'prospects_prospects.id')
                ->where('mailing_messages.status', 'sent')
                ->where('mailing_message_events.event_type', $rule['event_type']);

            if (! empty($rule['campaign_id'])) {
                $sub->where('mailing_messages.campaign_id', (int) $rule['campaign_id']);
            }

            if (! empty($rule['within_days'])) {
                $sub->where(
                    'mailing_message_events.occurred_at',
                    '>=',
                    now()->subDays((int) $rule['within_days'])
                );
            }
        });
    }

    private function applyProspectField(Builder $query, array $rule): void
    {
        $field = $rule['field'] ?? '';

        if (! in_array($field, self::ALLOWED_FIELDS, true)) {
            throw new \InvalidArgumentException(
                "Field '{$field}' is not allowed in segment filters. Allowed: " . implode(', ', self::ALLOWED_FIELDS)
            );
        }

        $operator = $rule['operator'] ?? '=';
        $value    = $rule['value'];

        if ($operator === 'in') {
            $query->whereIn("prospects_prospects.{$field}", (array) $value);
        } else {
            $query->where("prospects_prospects.{$field}", $value);
        }
    }
}
