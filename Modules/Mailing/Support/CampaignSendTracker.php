<?php

namespace Modules\Mailing\Support;

/**
 * Remembers, in the current user's session, the sends they launched so the panel-wide
 * CampaignProgressWatcher can report when they finish (CLA-680).
 *
 * Entries are {type: 'dispatch'|'campaign', value, at}. The inline path only knows the job's
 * dispatch key at launch; the campaign appears later. The list is capped and entries that
 * never resolve expire, so a lost job cannot keep the panel polling forever.
 */
class CampaignSendTracker
{
    public const MAX_ENTRIES = 20;

    public const TTL_SECONDS = 1800;

    public static function trackDispatch(string $dispatchKey): void
    {
        self::add('dispatch', $dispatchKey);
    }

    public static function trackCampaign(int $campaignId): void
    {
        self::add('campaign', $campaignId);
    }

    /** @return array<int, array{type: string, value: string|int, at: int}> */
    public static function pending(): array
    {
        $entries = array_values(array_filter(
            session()->get(self::key(), []),
            fn (array $e): bool => $e['at'] > now()->timestamp - self::TTL_SECONDS,
        ));

        session()->put(self::key(), $entries);

        return $entries;
    }

    public static function forget(string $type, string|int $value): void
    {
        session()->put(self::key(), array_values(array_filter(
            self::pending(),
            fn (array $e): bool => ! ($e['type'] === $type && $e['value'] === $value),
        )));
    }

    private static function add(string $type, string|int $value): void
    {
        $entries = self::pending();
        $entries[] = ['type' => $type, 'value' => $value, 'at' => now()->timestamp];

        session()->put(self::key(), array_slice($entries, -self::MAX_ENTRIES));
    }

    /** Per user, so a session reused by another login never sees someone else's sends. */
    private static function key(): string
    {
        return 'mailing.campaign_send_tracker.' . (auth()->id() ?? 'guest');
    }
}
