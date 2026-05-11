<?php

declare(strict_types=1);

namespace Modules\Safety\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Models\Inspection;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateSafetyPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $inspectionId) {}

    public function handle(): void
    {
        $inspection = Inspection::with(['answers.question', 'checklist'])->find($this->inspectionId);

        if (!$inspection) {
            return;
        }

        // Cargar vista en neerlandés
        $pdf = Pdf::loadView('safety::pdf.inspection-report-nl', [
            'inspection' => $inspection,
            // Recuperar el usuario del Core Module
            'user' => \Modules\Core\Models\User::find($inspection->user_id) 
        ]);

        $fileName = sprintf('werkplekinspectie_%s_%s.pdf', 
            $inspection->project_id, 
            $inspection->completed_at->format('Ymd_His')
        );
        $filePath = "safety-inspections/{$inspection->id}/{$fileName}";

        Storage::disk('public')->put($filePath, $pdf->output());

        $inspection->update(['pdf_path' => $filePath]);
    }
}
