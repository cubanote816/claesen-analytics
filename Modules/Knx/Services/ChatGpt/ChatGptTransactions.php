<?php

declare(strict_types=1);

namespace Modules\Knx\Services\ChatGpt;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * A sign-in in flight: state, nonce and PKCE verifier live server-side only, bound to
 * the employee who started it, for 10 minutes, and can be consumed ONCE.
 */
final class ChatGptTransactions
{
    private const TTL_SECONDS = 600;

    /** @return array{state: string, nonce: string, verifier: string, challenge: string} */
    public function begin(int $employeeId): array
    {
        $state = Str::random(48);
        $nonce = Str::random(48);
        $verifier = Str::random(64);

        Cache::put($this->key($state), [
            'employee_id' => $employeeId,
            'nonce' => $nonce,
            'verifier' => $verifier,
        ], self::TTL_SECONDS);

        return [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        ];
    }

    /**
     * Consume a state. Whatever the outcome it is gone afterwards: expired, unknown,
     * already used, or started by another employee all fail the same way.
     *
     * @return array{nonce: string, verifier: string}
     */
    public function pull(string $state, int $employeeId): array
    {
        $transaction = null;
        if ($state !== '') {
            // Read-and-delete under a lock: a concurrent consumer of the same state finds the
            // lock taken (or, after it, nothing left) and fails like any used state.
            $lock = Cache::lock($this->key($state).':lock', 10);
            if ($lock->get()) {
                try {
                    $transaction = Cache::get($this->key($state));
                    Cache::forget($this->key($state));
                } finally {
                    $lock->release();
                }
            }
        }

        if (! is_array($transaction) || ($transaction['employee_id'] ?? null) !== $employeeId) {
            throw new ChatGptFailed('state_invalid', 'The sign-in state is missing, expired, used or not yours.');
        }

        return ['nonce' => $transaction['nonce'], 'verifier' => $transaction['verifier']];
    }

    private function key(string $state): string
    {
        return 'knx:chatgpt:tx:'.hash('sha256', $state);
    }
}
