<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\FieldOps\Http\Requests\ReplaceLuminaireRequest;
use Modules\FieldOps\Http\Requests\StoreLuminaireRequest;
use Modules\FieldOps\Http\Requests\UpdateLuminaireRequest;
use Modules\FieldOps\Http\Resources\MaintenanceRecordResource;
use Modules\FieldOps\Http\Resources\LuminaireResource;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminairePosition;
use Modules\FieldOps\Services\LuminaireReplacementService;

class LuminaireController extends Controller
{
    public function showFromBackoffice(Request $request, Luminaire $luminaire): JsonResponse
    {
        $this->authorizeBackofficeEditor($request);

        return $this->show($luminaire);
    }

    public function storeFromBackoffice(StoreLuminaireRequest $request): JsonResponse
    {
        $this->authorizeBackofficeEditor($request);

        return $this->store($request);
    }

    public function updateFromBackoffice(UpdateLuminaireRequest $request, Luminaire $luminaire): JsonResponse
    {
        $this->authorizeBackofficeEditor($request);

        return $this->update($request, $luminaire);
    }

    public function replaceFromBackoffice(
        ReplaceLuminaireRequest $request,
        Luminaire $luminaire,
        LuminaireReplacementService $replacementService,
    ): JsonResponse {
        $this->authorizeBackofficeEditor($request);

        return $this->replace($request, $luminaire, $replacementService);
    }

    public function show(Luminaire $luminaire): \Illuminate\Http\JsonResponse
    {
        $luminaire->load('luminaireType', 'subgroup', 'createdBy', 'position');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireResource($luminaire),
        ]);
    }

    public function store(StoreLuminaireRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();
        $data['serial_number'] = $this->resolveSerialNumber($data['serial_number'] ?? null);
        $data = $this->applyPositionAuditMetadata($data, $this->resolveEditorSource($request), null, $request->user()?->id);

        $create = function () use ($data, $request): Luminaire {
            return Luminaire::create(array_merge(
                $data,
                ['created_by_user_id' => $request->user()->id],
            ));
        };

        $luminaire = isset($data['luminaire_position_id'])
            ? DB::transaction(function () use ($data, $create): Luminaire {
                $position = LuminairePosition::query()->lockForUpdate()->findOrFail($data['luminaire_position_id']);

                if ($position->currentInstallation()->exists()) {
                    throw ValidationException::withMessages([
                        'luminaire_position_id' => __('fieldops::resource.luminaires.position_not_vacant'),
                    ]);
                }

                return $create();
            })
            : $create();
        $luminaire->load('luminaireType', 'subgroup', 'createdBy', 'position');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireResource($luminaire),
        ], 201);
    }

    public function update(UpdateLuminaireRequest $request, Luminaire $luminaire): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();
        $touchesPosition = array_key_exists('frame_x', $data) || array_key_exists('frame_y', $data);

        // Merge info translations locale-by-locale to avoid overwriting untouched locales
        if (isset($data['info'])) {
            $data['info'] = array_merge($luminaire->getTranslations('info'), $data['info']);
        }

        // When moving to a different frame without an explicit frame_position,
        // auto-assign max+1 within the destination frame.
        if (isset($data['luminaire_frame_id'])
            && (int) $data['luminaire_frame_id'] !== (int) $luminaire->luminaire_frame_id
            && !array_key_exists('frame_position', $data)
        ) {
            $max = LuminairePosition::where('luminaire_frame_id', $data['luminaire_frame_id'])->max('frame_position');
            $data['frame_position'] = $max ? $max + 1 : 1;
        }

        if (! $touchesPosition) {
            $luminaire->update($data);
            $luminaire->load('luminaireType', 'subgroup', 'createdBy', 'position');

            return response()->json([
                'success' => true,
                'data'    => new LuminaireResource($luminaire),
            ]);
        }

        // CLA-501: editorSource is derived from the server's own auth context
        // (see resolveEditorSource()), never from the client-supplied
        // X-FieldOps-Editor header. The version check below now runs
        // unconditionally — it used to be skipped entirely whenever the
        // (spoofable) header claimed 'frontend', which defeated the guard for
        // exactly the client most exposed to lost updates (poor field
        // connectivity). Locking the row inside a transaction (mirroring
        // store()'s existing pattern) closes the remaining TOCTOU: without it,
        // two near-simultaneous requests could both read the same version and
        // both write, each unaware of the other.
        $editorSource = $this->resolveEditorSource($request);
        $expectedVersion = (int) ($request->input('position_version') ?? $this->normalizePositionVersion(
            $luminaire->position?->position_version ?? $luminaire->position_version,
        ));

        $result = DB::transaction(function () use ($luminaire, $data, $editorSource, $request, $expectedVersion) {
            $locked = Luminaire::query()->lockForUpdate()->findOrFail($luminaire->getKey());
            $currentVersion = $this->normalizePositionVersion($locked->position?->position_version ?? $locked->position_version);

            if ($expectedVersion !== $currentVersion) {
                return ['conflict' => true, 'current_version' => $currentVersion];
            }

            $locked->update($this->applyPositionAuditMetadata($data, $editorSource, $locked, $request->user()?->id));

            return ['conflict' => false, 'luminaire' => $locked];
        });

        if ($result['conflict']) {
            return response()->json([
                'message' => __('fieldops::resource.luminaires.position_conflict'),
                'current_position_version' => $result['current_version'],
            ], 409);
        }

        $luminaire = $result['luminaire'];
        $luminaire->load('luminaireType', 'subgroup', 'createdBy', 'position');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireResource($luminaire),
        ]);
    }

    public function replace(
        ReplaceLuminaireRequest $request,
        Luminaire $luminaire,
        LuminaireReplacementService $replacementService,
    ): JsonResponse {
        $result = $replacementService->replace(
            $luminaire,
            $request->validated(),
            $request->user()?->id,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'previous_luminaire' => new LuminaireResource($result['previous']),
                'current_luminaire' => new LuminaireResource($result['current']),
                'maintenance_record' => new MaintenanceRecordResource($result['maintenance']),
            ],
        ], 201);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function applyPositionAuditMetadata(array $data, string $source, ?Luminaire $current, ?int $userId): array
    {
        $touchesPosition = array_key_exists('frame_x', $data) || array_key_exists('frame_y', $data);

        if (! $touchesPosition && $current === null) {
            return $data;
        }

        $effectiveSource = $source === 'frontend' ? 'frontend' : 'backoffice';
        $data['position_version'] = $current ? ($this->normalizePositionVersion($current->position_version) + 1) : 1;
        $data['position_source'] = $effectiveSource;

        if ($effectiveSource === 'frontend') {
            $data['position_verified_at'] = now();
            $data['position_verified_by_user_id'] = $userId;
        } else {
            $data['position_verified_at'] = null;
            $data['position_verified_by_user_id'] = null;
        }

        return $data;
    }

    // CLA-501: the previous X-FieldOps-Editor request header was entirely
    // client-supplied and trivially spoofable — any caller could claim
    // 'frontend' and have their position marked field-verified, or bypass the
    // optimistic-concurrency check outright (it only ran for non-'frontend').
    // The field app (Claesen-Sport-updateing) authenticates cross-origin via a
    // Sanctum bearer token; the Filament backoffice's own positioning UI is a
    // same-origin browser fetch authenticated via the session cookie (Sanctum
    // stateful guard), which resolves to a TransientToken, not a real
    // PersonalAccessToken (same distinction already used in
    // Modules/Safety/Http/Middleware/EnsureSafetyAccess.php). Neither path can
    // be forged by simply setting a header — a caller must actually
    // authenticate through the corresponding channel.
    private function resolveEditorSource(Request $request): string
    {
        return $request->user()?->currentAccessToken() instanceof PersonalAccessToken
            ? 'frontend'
            : 'backoffice';
    }

    private function normalizePositionVersion(mixed $version): int
    {
        $numeric = (int) ($version ?? 0);

        return $numeric > 0 ? $numeric : 1;
    }

    private function resolveSerialNumber(mixed $serialNumber): string
    {
        $serial = trim((string) $serialNumber);

        if ($serial !== '') {
            return mb_substr($serial, 0, 50);
        }

        return 'AUTO-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }

    private function authorizeBackofficeEditor(Request $request): void
    {
        abort_unless(
            $request->user()?->hasAnyRole(['super_admin', 'admin']) ?? false,
            403,
        );
    }

    public function destroy(Luminaire $luminaire): \Illuminate\Http\Response
    {
        $luminaire->delete();

        return response()->noContent();
    }
}
