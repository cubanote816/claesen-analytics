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
use Modules\Safety\Jobs\SendInspectionReportMailJob;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateSafetyPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $inspectionId,
        public ?int $userId = null
    ) {}

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

        $prefix = $inspection->type === 'incident' ? 'incidentenrapport' : 'werkplekinspectie';
        $fileName = sprintf('%s_%s_%s.pdf', 
            $prefix,
            $inspection->project_id, 
            $inspection->completed_at->format('Ymd_His')
        );
        $filePath = "safety-inspections/{$inspection->id}/{$fileName}";

        Storage::disk(config('safety.disk'))->put($filePath, $pdf->output());

        $inspection->update(['pdf_path' => $filePath]);

        SendInspectionReportMailJob::dispatch($this->inspectionId);

        // Notificar al usuario que lo solicitó
        if ($this->userId) {
            $recipient = \Modules\Core\Models\User::find($this->userId);
            if ($recipient) {
                \Filament\Notifications\Notification::make()
                    ->title(__('safety::inspections.actions.download_pdf') . ' Gereed')
                    ->body("Het rapport voor project {$inspection->project_id} is nu beschikbaar.")
                    ->success()
                    ->actions([
                        \Filament\Actions\Action::make('download')
                            ->label(__('safety::inspections.actions.download_pdf'))
                            ->url(route('safety.admin.pdf', ['inspection' => $inspection->id]))
                            ->openUrlInNewTab()
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($recipient);
            }
        }
    }
}
