<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Safety\Http\Requests\StoreInspectionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Jobs\GenerateSafetyPdfJob;
use Filament\Notifications\Notification;
use Modules\Core\Models\User;
use Illuminate\Support\Facades\Log;

class InspectionController extends Controller
{
    public function store(StoreInspectionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $projectId = trim($validated['project_id']);
        $userId = $request->user()->id;

        $inspection = DB::transaction(function () use ($validated, $request, $userId, $projectId) {
            $inspection = Inspection::create([
                'user_id'            => $userId,
                'checklist_id'       => $validated['checklist_id'],
                'type'               => $validated['type'],
                'incident_worker_id' => $validated['incident_worker_id'] ?? null,
                'project_id'         => $projectId,
                'completed_at'       => now(),
            ]);

            // Conditionally sync present workers ONLY if type is inspection
            if ($validated['type'] === 'inspection' && !empty($validated['present_workers'])) {
                $inspection->presentWorkers()->sync($validated['present_workers']);
            }

            foreach ($validated['answers'] as $answer) {
                $questionId = $answer['question_id'];
                $photoPath = null;

                $statusMap = [
                    'YES' => 'ok',
                    'NO'  => 'nok',
                    'NA'  => 'na'
                ];

                if ($request->hasFile("photos.{$questionId}")) {
                    $file = $request->file("photos.{$questionId}");
                    $photoPath = $file->store("safety-inspections/{$inspection->id}", config('safety.disk'));
                }

                $inspection->answers()->create([
                    'question_id' => $questionId,
                    'status'      => $statusMap[$answer['value']] ?? 'na',
                    'remark'      => $answer['remark'] ?? null,
                    'photo_path'  => $photoPath,
                ]);
            }

            return $inspection;
        });

        try {
            // Despachar la generación del PDF asíncronamente
            GenerateSafetyPdfJob::dispatch($inspection->id);

            // Notificar a los Super Admins
            $admins = User::role('super_admin')->get();
            if ($admins->count() > 0) {
                $title = $inspection->type === 'incident' ? 'Nieuw Incidentenrapport' : 'Nieuwe werkplekinspectie';
                $body = $inspection->type === 'incident'
                    ? "Medewerker **{$request->user()->name}** heeft een incident gemeld op project **{$projectId}**."
                    : "Inspecteur **{$request->user()->name}** heeft een inspectie ingediend for project **{$projectId}**.";

                $notification = Notification::make()
                    ->title($title)
                    ->icon('heroicon-o-shield-check')
                    ->body($body)
                    ->success()
                    ->viewData(['module' => 'safety'])
                    ->actions([
                        \Filament\Actions\Action::make('view')
                            ->label('Bekijken')
                            ->url(\Modules\Safety\Filament\Resources\InspectionResource::getUrl('view', ['record' => $inspection]))
                    ]);

                $notification->send($admins);
            }
        } catch (\Exception $e) {
            Log::error("Post-inspection tasks failed: " . $e->getMessage());
            // We don't fail the request if notifications or PDF fails
        }

        return response()->json([
            'message' => $validated['type'] === 'incident' ? 'Incident succesvol gemeld.' : 'Inspectie succesvol opgeslagen.',
            'data'    => ['inspection_id' => $inspection->id],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $inspections = Inspection::where('user_id', $request->user()->id)
            ->orderBy('completed_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($inspection) {
                $hasDefects = $inspection->answers->contains('status', 'nok');

                return [
                    'id'      => $inspection->id,
                    'project' => $inspection->project_id,
                    'date'    => $inspection->completed_at ? $inspection->completed_at->format('Y-m-d H:i') : now()->format('Y-m-d H:i'),
                    'status'  => $hasDefects ? 'DEFECTEN' : 'VEILIG',
                    'type'    => $hasDefects ? 'warning' : 'success',
                ];
            });

        return response()->json([
            'data' => $inspections
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $totalInspections = Inspection::where('user_id', $userId)->count();
        $defectsCount = Inspection::where('user_id', $userId)
            ->whereHas('answers', function ($query) {
                $query->where('status', 'nok');
            })->count();

        return response()->json([
            'data' => [
                'total'   => $totalInspections,
                'defects' => $defectsCount,
            ]
        ]);
    }
}
