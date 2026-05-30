<?php

namespace Modules\Mailing\Services;

use Illuminate\Support\Facades\Log;
use Modules\Mailing\Models\ContactPreference;
use Modules\Prospects\Models\Prospect;

/**
 * Manages per-category subscription preferences for prospects.
 *
 * Design invariants:
 *  - Opt-out model: no record = subscribed (default true).
 *  - updatePreferences() NEVER touches unsubscribed_at.
 *    Global unsubscribe and category preferences are independent.
 *  - Only categories listed in config('mailing.preference_categories')
 *    are allowed to reach the database.
 */
class PreferenceService
{
    /**
     * Returns the current preferences for all known categories.
     * Categories without a DB record default to true (subscribed).
     *
     * @return array<string, bool>  e.g. ['newsletter' => true, 'offers' => false]
     */
    public function getPreferences(Prospect $prospect): array
    {
        $categories = array_keys(config('mailing.preference_categories', []));

        $existing = ContactPreference::where('prospect_id', $prospect->id)
            ->whereIn('category', $categories)
            ->pluck('subscribed', 'category')
            ->map(fn ($v) => (bool) $v)
            ->all();

        // Default to true for categories without a record (opt-out model)
        return array_merge(array_fill_keys($categories, true), $existing);
    }

    /**
     * Persists preferences for known categories only.
     *
     * Does NOT modify unsubscribed_at — category preferences are independent
     * of the global unsubscribe flag. Activating a category never re-subscribes
     * a globally unsubscribed prospect.
     *
     * Unknown categories are silently ignored with a log warning.
     *
     * @param  array<string, bool>  $categories
     */
    public function updatePreferences(Prospect $prospect, array $categories): void
    {
        $allowlist = array_keys(config('mailing.preference_categories', []));

        foreach ($categories as $category => $subscribed) {
            if (! in_array($category, $allowlist, true)) {
                Log::warning('PreferenceService: ignoring unknown category', [
                    'category'    => $category,
                    'prospect_id' => $prospect->id,
                ]);
                continue;
            }

            ContactPreference::updateOrCreate(
                ['prospect_id' => $prospect->id, 'category' => $category],
                ['subscribed'  => (bool) $subscribed]
            );
        }
    }

    /**
     * Returns whether the prospect is subscribed to a given category.
     * Defaults to true (subscribed) when no explicit preference exists.
     */
    public function isSubscribedToCategory(Prospect $prospect, string $category): bool
    {
        $record = ContactPreference::where('prospect_id', $prospect->id)
            ->where('category', $category)
            ->first();

        return $record === null || (bool) $record->subscribed;
    }
}
