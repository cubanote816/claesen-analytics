<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

use function Livewire\on;

/**
 * The page (`memo.path` / `memo.method`) of the last Livewire snapshot whose checksum was verified.
 *
 * Fed by Livewire's `snapshot-verified` hook, which fires right after the checksum check and before the
 * component is hydrated, so while a component hydrates this is that component's own page. Only the real
 * update route records: anywhere else the snapshot is client input. Scoped, never static, and bound to the
 * request that recorded it, so it cannot outlive that request in a worker or a test.
 */
final class VerifiedLivewirePage
{
    private ?Request $request = null;

    private ?string $path = null;

    private ?string $method = null;

    public static function listen(): void
    {
        on('snapshot-verified', function (mixed $snapshot): void {
            if (! app(HandleRequests::class)->isLivewireRoute()) {
                return;
            }

            app(self::class)->record(request(), $snapshot);
        });

        on('flush-state', fn () => app(self::class)->forget());
    }

    public function record(Request $request, mixed $snapshot): void
    {
        $path = is_array($snapshot) ? ($snapshot['memo']['path'] ?? null) : null;
        $method = is_array($snapshot) ? ($snapshot['memo']['method'] ?? null) : null;

        // Always overwrite: a snapshot without a usable page must not inherit the previous component's.
        $this->request = $request;
        $this->path = is_string($path) ? $path : null;
        $this->method = is_string($method) ? $method : null;
    }

    public function forget(): void
    {
        $this->request = $this->path = $this->method = null;
    }

    /** @return array{0: string, 1: string}|null [path, method] verified during the given request. */
    public function for(Request $request): ?array
    {
        if ($this->request !== $request || $this->path === null || $this->method === null) {
            return null;
        }

        return [$this->path, $this->method];
    }
}
