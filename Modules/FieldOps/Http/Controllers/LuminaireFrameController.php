<?php

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\FieldOps\Http\Resources\LuminaireResource;
use Modules\FieldOps\Models\LuminaireFrame;

class LuminaireFrameController extends Controller
{
    public function luminaires(LuminaireFrame $frame): \Illuminate\Http\JsonResponse
    {
        $frame->load('luminaires.luminaireType', 'luminaires.subgroup');

        return response()->json([
            'success' => true,
            'data'    => LuminaireResource::collection($frame->luminaires),
        ]);
    }
}
