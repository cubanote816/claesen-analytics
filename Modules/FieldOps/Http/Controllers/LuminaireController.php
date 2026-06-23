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
        $luminaire = Luminaire::create(array_merge(
            $request->validated(),
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

    public function destroy(Luminaire $luminaire): \Illuminate\Http\Response
    {
        $luminaire->delete();

        return response()->noContent();
    }
}
