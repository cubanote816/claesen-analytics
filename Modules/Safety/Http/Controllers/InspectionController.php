<?php

declare(strict_types=1);

namespace Modules\Safety\Http\Controllers;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Safety\Http\Requests\StoreInspectionRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Safety\Models\Answer;
use Modules\Safety\Models\Inspection;
use Modules\Safety\Jobs\GenerateSafetyPdfJob;
use Filament\Notifications\Notification;
use Modules\Core\Models\User;
use Illuminate\Support\Facades\Log;

class InspectionController extends Controller
{
    private const IDEMPOTENCY_INDEX = 'safety_inspections_user_idempotency_unique';

    public function store(StoreInspectionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $projectId = trim($validated['project_id']);
        $userId    = $request->user()->id;

        // Compute hash ONCE — post-validation, pre-transaction
        $payloadHash = !empty($validated['idempotency_key'])
            ? $this->computeHash($request, $validated, $projectId)
            : null;

        // Idempotency pre-check
        if (!empty($validated['idempotency_key'])) {
            $existing = Inspection::where('user_id', $userId)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if ($existing) {
                return $this->idempotentResponse($existing, $payloadHash);
            }
        }

        $photoStorageFailures = [];

        try {
            $inspection = DB::transaction(function () use ($validated, $request, $userId, $projectId, $payloadHash, &$photoStorageFailures) {
                $inspection = Inspection::create([
                    'user_id'            => $userId,
                    'checklist_id'       => $validated['checklist_id'],
                    'type'               => $validated['type'],
                    'incident_worker_id' => $validated['type'] === 'incident'
                                             ? ($validated['incident_worker_id'] ?? null)
                                             : null,
                    'project_id'         => $projectId,
                    'idempotency_key'    => $validated['idempotency_key'] ?? null,
                    'completed_at'       => now(),
                    'payload_hash'       => $payloadHash,
                ]);

                // Sync present workers ONLY for inspection type
                if ($validated['type'] === 'inspection' && !empty($validated['present_workers'])) {
                    $inspection->presentWorkers()->sync($validated['present_workers']);
                }

                $statusMap = ['YES' => 'ok', 'NO' => 'nok', 'NA' => 'na'];

                foreach ($validated['answers'] as $answer) {
                    $questionId = $answer['question_id'];
                    $photoPath  = null;

                    if ($request->hasFile("photos.{$questionId}")) {
                        $file = $request->file("photos.{$questionId}");
                        try {
                            $photoPath = $file->store("safety-inspections/{$inspection->id}", config('safety.disk'));
                        } catch (\Exception $e) {
                            Log::error("Photo storage failed for inspection {$inspection->id}, question {$questionId}: " . $e->getMessage());
                            $photoStorageFailures[] = $questionId;
                            $photoPath = null;
                        }
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

        } catch (UniqueConstraintViolationException $e) {
            // Only recover for the idempotency index — re-throw any other unique constraint
            if (!str_contains($e->getMessage(), self::IDEMPOTENCY_INDEX)
                || empty($validated['idempotency_key'])) {
                throw $e;
            }

            $existing = Inspection::where('user_id', $userId)
                ->where('idempotency_key', $validated['idempotency_key'])
                ->first();

            if (!$existing) throw $e; // inconsistency — re-throw

            return $this->idempotentResponse($existing, $payloadHash);
        }

        // Record adoption events
        try {
            \Modules\Safety\Models\SafetyAdoptionEvent::create([
                'user_id' => $userId,
                'event_type' => 'inspection_completed',
                'project_id' => $projectId,
                'metadata' => ['inspection_id' => $inspection->id, 'type' => $validated['type']],
            ]);

            if (!empty($photoStorageFailures)) {
                \Modules\Safety\Models\SafetyAdoptionEvent::create([
                    'user_id' => $userId,
                    'event_type' => 'photo_upload_failed',
                    'project_id' => $projectId,
                    'metadata' => ['failures_count' => count($photoStorageFailures), 'questions' => $photoStorageFailures],
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Failed to record adoption events: " . $e->getMessage());
        }

        try {
            GenerateSafetyPdfJob::dispatch($inspection->id);

            $admins = User::role('super_admin')->get();
            if ($admins->count() > 0) {
                $title = $inspection->type === 'incident' ? 'Nieuw Incidentenrapport' : 'Nieuwe werkplekinspectie';
                $body  = $inspection->type === 'incident'
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
        }

        $responseData = ['inspection_id' => $inspection->id];
        if (!empty($photoStorageFailures)) {
            $responseData['photo_warnings'] = $photoStorageFailures;
        }

        return response()->json([
            'message' => $validated['type'] === 'incident' ? 'Incident succesvol gemeld.' : 'Inspectie succesvol opgeslagen.',
            'data'    => $responseData,
        ], 201);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function canonicalPayload(array $validated, string $projectId, Request $request): array
    {
        $type = $validated['type'];

        $answers = collect($validated['answers'])
            ->sortBy(fn($a) => (int) $a['question_id'])
            ->values()
            ->map(fn($a) => [
                'question_id' => (int) $a['question_id'],
                'value'       => $a['value'],
                'remark'      => ($r = trim($a['remark'] ?? '')) !== '' ? $r : null,
            ])
            ->toArray();

        $validQIds = collect($answers)->pluck('question_id')->all();

        $photos = [];
        foreach ($request->file('photos', []) as $qId => $file) {
            if (!in_array((int) $qId, $validQIds, true)) continue;
            if (!$file || !$file->isValid()) {
                throw new \RuntimeException("Invalid photo file for question {$qId}");
            }
            $realPath = $file->getRealPath();
            if ($realPath === false || $realPath === '') {
                throw new \RuntimeException("Cannot read photo path for question {$qId}");
            }
            $hash = hash_file('sha256', $realPath);
            if ($hash === false) {
                throw new \RuntimeException("Hash computation failed for question {$qId}");
            }
            $photos[] = ['question_id' => (int) $qId, 'sha256' => $hash];
        }
        usort($photos, fn($a, $b) => $a['question_id'] <=> $b['question_id']);

        $payload = [
            'checklist_id' => (int) $validated['checklist_id'],
            'type'         => $type,
            'project_id'   => $projectId,
            'answers'      => $answers,
            'photos'       => $photos,
        ];

        if ($type === 'inspection') {
            $payload['present_workers'] = collect($validated['present_workers'] ?? [])
                ->map(fn($id) => (string) $id)
                ->sort()
                ->values()
                ->toArray();
        } else {
            $payload['incident_worker_id'] = (string) ($validated['incident_worker_id'] ?? '');
        }

        return $payload;
    }

    private function computeHash(Request $request, array $validated, string $projectId): string
    {
        return hash('sha256', json_encode(
            $this->canonicalPayload($validated, $projectId, $request),
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    private function idempotentResponse(Inspection $existing, ?string $currentHash): JsonResponse
    {
        $message = $existing->type === 'incident' ? 'Incident succesvol gemeld.' : 'Inspectie succesvol opgeslagen.';

        if ($existing->payload_hash === null) {
            // Records without a stored fingerprint (typically created before SAF-019)
            // cannot be compared with the current payload. For backward compatibility,
            // any request reusing their idempotency key returns the existing 200 response,
            // even when the submitted payload differs. This behavior is intentional.
            return response()->json(['message' => $message, 'data' => ['inspection_id' => $existing->id]], 200);
        }

        if ($existing->payload_hash === $currentHash) {
            return response()->json(['message' => $message, 'data' => ['inspection_id' => $existing->id]], 200);
        }

        try {
            \Modules\Safety\Models\SafetyAdoptionEvent::create([
                'user_id' => $existing->user_id,
                'event_type' => 'inspection_payload_conflict',
                'project_id' => $existing->project_id,
                'metadata' => ['inspection_id' => $existing->id]
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to record conflict adoption event: " . $e->getMessage());
        }

        return response()->json([
            'error'         => 'inspection_payload_conflict',
            'inspection_id' => $existing->id,
        ], 409);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Remaining endpoints (unchanged)
    // ──────────────────────────────────────────────────────────────────────────

    public function servePhoto(Inspection $inspection, Answer $answer): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        if ($answer->inspection_id !== $inspection->id) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        Gate::authorize('viewPhoto', $inspection);

        if (! $answer->photo_path) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $disk = Storage::disk(config('safety.disk'));

        if (! $disk->exists($answer->photo_path)) {
            return response()->json(['message' => 'Not found.'], 404);
        }

        $mimeType = $disk->mimeType($answer->photo_path) ?: 'application/octet-stream';

        return response()->stream(function () use ($disk, $answer) {
            $stream = $disk->readStream($answer->photo_path);
            if ($stream === false) {
                return;
            }
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type'  => $mimeType,
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    public function downloadPdf(Inspection $inspection): \Symfony\Component\HttpFoundation\StreamedResponse|JsonResponse
    {
        Gate::authorize('downloadPdf', $inspection);

        if ($inspection->pdf_path === null) {
            return response()->json(['pdf_status' => 'pending'], 202);
        }

        $disk = Storage::disk(config('safety.disk'));

        if (! $disk->exists($inspection->pdf_path)) {
            return response()->json(['pdf_status' => 'failed'], 404);
        }

        return response()->streamDownload(function () use ($disk, $inspection) {
            $stream = $disk->readStream($inspection->pdf_path);
            if ($stream === false) {
                return;
            }
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, basename($inspection->pdf_path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function show(Inspection $inspection): JsonResponse
    {
        Gate::authorize('view', $inspection);

        $inspection->load([
            'checklist',
            'user',
            'incidentWorker',
            'presentWorkers',
            'answers.question',
        ]);

        $disk = config('safety.disk');

        $pdfStatus = match (true) {
            $inspection->pdf_path === null                              => 'pending',
            Storage::disk($disk)->exists($inspection->pdf_path)        => 'available',
            default                                                     => 'failed',
        };

        $pdfUrl = $inspection->pdf_path
            ? url("api/v1/safety/inspections/{$inspection->id}/pdf")
            : null;

        $answers = $inspection->answers->map(function ($answer) use ($inspection) {
            $photoUrl = $answer->photo_path
                ? url("api/v1/safety/inspections/{$inspection->id}/answers/{$answer->id}/photo")
                : null;

            return [
                'id'        => $answer->id,
                'status'    => $answer->status,
                'remark'    => $answer->remark,
                'photo_url' => $photoUrl,
                'question'  => [
                    'id'       => $answer->question->id,
                    'text'     => $answer->question->text_nl,
                    'category' => $answer->question->category,
                ],
            ];
        });

        return response()->json([
            'data' => [
                'id'              => $inspection->id,
                'type'            => $inspection->type,
                'project_id'      => $inspection->project_id,
                'completed_at'    => $inspection->completed_at?->toIso8601String(),
                'pdf_status'      => $pdfStatus,
                'pdf_url'         => $pdfUrl,
                'inspector'       => [
                    'id'    => $inspection->user->id,
                    'name'  => $inspection->user->name,
                    'email' => $inspection->user->email,
                ],
                'incident_worker' => $inspection->incidentWorker ? [
                    'id'   => $inspection->incidentWorker->id,
                    'name' => $inspection->incidentWorker->name,
                ] : null,
                'present_workers' => $inspection->presentWorkers->map(fn ($u) => [
                    'id'   => $u->id,
                    'name' => $u->name,
                ]),
                'checklist'       => [
                    'id'   => $inspection->checklist->id,
                    'name' => $inspection->checklist->name,
                    'type' => $inspection->checklist->type,
                ],
                'answers'         => $answers,
            ],
        ]);
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
        $defectsCount     = Inspection::where('user_id', $userId)
            ->whereHas('answers', function ($query) {
                $query->where('status', 'nok');
            })->count();

        return response()->json([
            'data' => [
                'total'   => $totalInspections,
                'defects' => $defectsCount,
            ],
        ]);
    }
}
