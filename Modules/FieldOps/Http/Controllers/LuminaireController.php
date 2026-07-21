<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\FieldOps\Http\Requests\StoreLuminaireRequest;
use Modules\FieldOps\Http\Requests\UpdateLuminaireRequest;
use Modules\FieldOps\Http\Resources\LuminaireResource;
use Modules\FieldOps\Models\Luminaire;

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

    public function show(Luminaire $luminaire): \Illuminate\Http\JsonResponse
    {
        $luminaire->load('luminaireType', 'subgroup', 'createdBy');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireResource($luminaire),
        ]);
    }

    public function store(StoreLuminaireRequest $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();
        $data['serial_number'] = $this->resolveSerialNumber($data['serial_number'] ?? null);
        $data = $this->applyPositionAuditMetadata($data, $request->header('X-FieldOps-Editor', 'backoffice'), null, $request->user()?->id);

        $luminaire = Luminaire::create(array_merge(
            $data,
            ['created_by_user_id' => $request->user()->id],
        ));
        $luminaire->load('luminaireType', 'subgroup', 'createdBy');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireResource($luminaire),
        ], 201);
    }

    public function update(UpdateLuminaireRequest $request, Luminaire $luminaire): \Illuminate\Http\JsonResponse
    {
        $data = $request->validated();
        $touchesPosition = array_key_exists('frame_x', $data) || array_key_exists('frame_y', $data);
        $editorSource = $request->header('X-FieldOps-Editor', 'backoffice');
        $currentPositionVersion = $this->normalizePositionVersion($luminaire->position_version);

        // Merge info translations locale-by-locale to avoid overwriting untouched locales
        if (isset($data['info'])) {
            $data['info'] = array_merge($luminaire->getTranslations('info'), $data['info']);
        }

        if ($touchesPosition) {
            if ($editorSource !== 'frontend') {
                $expectedVersion = (int) ($request->input('position_version') ?? $currentPositionVersion);

                if ($expectedVersion !== $currentPositionVersion) {
                    return response()->json([
                        'message' => __('fieldops::resource.luminaires.position_conflict'),
                        'current_position_version' => $currentPositionVersion,
                    ], 409);
                }
            }

            $data = $this->applyPositionAuditMetadata(
                $data,
                $editorSource,
                $luminaire,
                $request->user()?->id,
            );
        }

        // When moving to a different frame without an explicit frame_position,
        // auto-assign max+1 within the destination frame.
        if (isset($data['luminaire_frame_id'])
            && (int) $data['luminaire_frame_id'] !== (int) $luminaire->luminaire_frame_id
            && !array_key_exists('frame_position', $data)
        ) {
            $max = Luminaire::where('luminaire_frame_id', $data['luminaire_frame_id'])->max('frame_position');
            $data['frame_position'] = $max ? $max + 1 : 1;
        }

        $luminaire->update($data);
        $luminaire->load('luminaireType', 'subgroup', 'createdBy');

        return response()->json([
            'success' => true,
            'data'    => new LuminaireResource($luminaire),
        ]);
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
