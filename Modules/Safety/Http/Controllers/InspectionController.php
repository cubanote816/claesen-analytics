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
        $perPage = min(
            (int) $request->query('per_page', config('safety.per_page')),
            config('safety.per_page_max')
        );

        $query = Inspection::with('answers')->orderBy('completed_at', 'desc');

        if (! $request->user()->hasRole('super_admin')) {
            $query->where('user_id', $request->user()->id);
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('completed_at', '>=', $from);
        }
        if ($until = $request->query('until')) {
            $query->whereDate('completed_at', '<=', $until);
        }

        $paginator = $query->paginate($perPage)->through(function ($inspection) {
            $hasDefects = $inspection->answers->contains('status', 'nok');

            if ($inspection->type === 'incident') {
                $status = 'INCIDENT';
                $color  = 'danger';
            } else {
                $status = $hasDefects ? 'DEFECTEN' : 'VEILIG';
                $color  = $hasDefects ? 'warning' : 'success';
            }

            return [
                'id'       => $inspection->id,
                'project'  => $inspection->project_id,
                'category' => $inspection->type,
                'date'     => $inspection->completed_at
                    ? $inspection->completed_at->format('Y-m-d H:i')
                    : now()->format('Y-m-d H:i'),
                'status'   => $status,
                'type'     => $color,
            ];
        });

        return response()->json($paginator);
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
