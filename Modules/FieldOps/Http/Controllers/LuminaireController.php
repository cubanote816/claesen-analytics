<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreLuminaireRequest;
use Modules\FieldOps\Http\Requests\UpdateLuminaireRequest;
use Modules\FieldOps\Http\Resources\LuminaireResource;
use Modules\FieldOps\Models\Luminaire;

class LuminaireController extends Controller
{
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
        $data = $this->applyPositionAuditMetadata($request->validated(), $request->header('X-FieldOps-Editor', 'backoffice'), null, $request->user()?->id);

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

        // Merge info translations locale-by-locale to avoid overwriting untouched locales
        if (isset($data['info'])) {
            $data['info'] = array_merge($luminaire->getTranslations('info'), $data['info']);
        }

        if ($touchesPosition) {
            $expectedVersion = (int) ($request->input('position_version') ?? 0);

            if ($expectedVersion !== (int) $luminaire->position_version) {
                return response()->json([
                    'message' => __('fieldops::resource.luminaires.position_conflict'),
                    'current_position_version' => $luminaire->position_version,
                ], 409);
            }

            $data = $this->applyPositionAuditMetadata(
                $data,
                $request->header('X-FieldOps-Editor', 'backoffice'),
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
        $data['position_version'] = $current ? ((int) $current->position_version + 1) : 1;
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

    public function destroy(Luminaire $luminaire): \Illuminate\Http\Response
    {
        $luminaire->delete();

        return response()->noContent();
    }
}
