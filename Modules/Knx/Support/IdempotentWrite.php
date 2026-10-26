<?php

declare(strict_types=1);

namespace Modules\Knx\Support;

use Illuminate\Database\UniqueConstraintViolationException;

/**
 * The one race a field `clientId` cannot avoid (CLA-609).
 *
 * The app works offline, so the same write can be retried while the original request is
 * still being processed. Both pass the "does this key exist already?" read, both try to
 * insert, and the unique index on `client_id` makes one of them lose.
 *
 * Losing must answer what the winner answered. A `500` would be the worst available
 * answer, because a `500` is exactly what the field app retries — the failure would feed
 * itself, forever.
 *
 * The recovery read has to happen **outside** the failed transaction: under REPEATABLE
 * READ a read inside it would replay the loser's own snapshot and never see the row that
 * beat it. Both callers here are called after Laravel has already rolled the transaction
 * back, which is why this is a helper and not a `try` inside each service.
 *
 * It lives in one place because three endpoints (devices, issues, visits) need the same
 * rule, and a subtle rule copied three times is a subtle rule that drifts.
 */
final class IdempotentWrite
{
    /**
     * Run a write that must be idempotent on a key the client generated.
     *
     * @template T
     *
     * @param  callable(): T  $write  the write itself, inside its transaction
     * @param  callable(): (T|null)  $recover  reads the row the winner wrote, or `null`
     *                                        when this key is not the reason it failed
     * @return T
     */
    public static function run(callable $write, callable $recover): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException $lost) {
            $winner = $recover();

            if ($winner === null) {
                // The key is not in the table, so something else broke the insert.
                // Answering as if we had lost the race would hide a real failure.
                throw $lost;
            }

            return $winner;
        }
    }
}
