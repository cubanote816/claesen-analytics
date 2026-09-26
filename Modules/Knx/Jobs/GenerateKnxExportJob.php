<?php

namespace Modules\Knx\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Modules\Knx\Models\KnxExport;
use Modules\Knx\Services\ExportService;
use Throwable;

/**
 * Generates a report file in the background, as §4.9 requires: the request itself
 * must not wait for a PDF.
 *
 * The export row is the contract with the caller — it is created `queued` by the
 * endpoint and this job is what turns it `ready` (with a path) or `failed`. A
 * failure is recorded on the row, never swallowed, because a report that never
 * appears is the worst possible outcome for the office.
 */
class GenerateKnxExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly int $exportId) {}

    public function handle(ExportService $exports): void
    {
        $export = KnxExport::query()->with('project')->find($this->exportId);

        if ($export === null) {
            return;
        }

        $path = 'knx/exports/'.$exports->filenameFor($export);

        Storage::disk('local')->put($path, $exports->csvFor($export));

        $export->forceFill([
            'status' => KnxExport::STATUS_READY,
            'path' => $path,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        KnxExport::query()
            ->whereKey($this->exportId)
            ->update(['status' => KnxExport::STATUS_FAILED]);

        Log::error('KNX export generation failed.', [
            'export_id' => $this->exportId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
