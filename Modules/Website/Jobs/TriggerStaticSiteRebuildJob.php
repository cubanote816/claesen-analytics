<?php

namespace Modules\Website\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// handle() is implemented in WEB-020 / CLA-141.
class TriggerStaticSiteRebuildJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly string $dispatchKey,
        public readonly string $reason,
        public readonly bool   $force,
    ) {}

    public function handle(): void
    {
        // Implemented in WEB-020.
    }
}
