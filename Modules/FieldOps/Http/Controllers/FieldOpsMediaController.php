<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Requests\StoreFieldOpsMediaRequest;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class FieldOpsMediaController extends Controller
{
    private const MODEL_MAP = [
        'complexes'         => Complex::class,
        'terrains'          => Terrain::class,
        'structures'        => Structure::class,
        'electrical-boards' => ElectricalBoard::class,
    ];

    public function store(StoreFieldOpsMediaRequest $request, string $modelType, int $modelId): \Illuminate\Http\JsonResponse
    {
        $modelClass = self::MODEL_MAP[$modelType];
        $model      = $modelClass::findOrFail($modelId);

        $media = $model
            ->addMediaFromRequest('file')
            ->toMediaCollection($request->validated('collection'));

        return response()->json([
            'success' => true,
            'data'    => $this->mediaPayload($media),
        ], 201);
    }

    public function show(\Illuminate\Http\Request $request, Media $media): HttpResponse
    {
        $this->assertFieldOpsMedia($media);

        $conversion = $request->query('conversion');
        $path       = $conversion && $media->hasGeneratedConversion($conversion)
            ? $media->getPath($conversion)
            : $media->getPath();

        return response()->file($path);
    }

    public function destroy(Media $media): \Illuminate\Http\Response
    {
        $this->assertFieldOpsMedia($media);

        $media->delete();

        return response()->noContent();
    }

    private function assertFieldOpsMedia(Media $media): void
    {
        abort_unless(in_array($media->model_type, self::MODEL_MAP, true), 404);
    }

    private function mediaPayload(Media $media): array
    {
        return [
            'id'        => $media->id,
            'name'      => $media->file_name,
            'mime_type' => $media->mime_type,
            'size'      => $media->size,
            'url'       => url("/api/v1/fieldops/media/{$media->id}"),
            'thumb_url' => $media->hasGeneratedConversion('thumb')
                ? url("/api/v1/fieldops/media/{$media->id}?conversion=thumb")
                : null,
        ];
    }
}
