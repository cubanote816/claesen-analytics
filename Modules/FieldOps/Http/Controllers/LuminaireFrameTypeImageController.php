<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class LuminaireFrameTypeImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('super_admin'), 403);

        $validated = $request->validate([
            'file' => ['required', 'image', 'max:10240'],
        ]);

        $path = $validated['file']->store('luminaire-frame-types', 'public');

        return response()->json([
            'success' => true,
            'data' => [
                'url' => Storage::disk('public')->url($path),
            ],
        ], 201);
    }
}
