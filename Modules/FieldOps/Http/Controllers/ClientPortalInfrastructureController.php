<?php

declare(strict_types=1);

namespace Modules\FieldOps\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;
use Modules\FieldOps\Services\FieldOpsTenantService;

/**
 * Read model for the external client portal.
 *
 * This is intentionally separate from the operational FieldOps resources: it
 * contains no author, CAFCA, access/safety, work-order or mutation data.
 */
class ClientPortalInfrastructureController extends Controller
{
    public function __construct(private readonly FieldOpsTenantService $tenants) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $this->tenants->isClientUser($user), 403);

        $locale = $request->getLocale();
        $complexes = $this->tenants
            ->scopeForUser(Complex::query(), $user, Complex::class)
            ->with([
                'terrains.terrainType',
                'terrains.structures' => fn ($query) => $this->tenants->scopeForUser($query->getQuery(), $user, Structure::class)
                    ->with([
                        'structureType',
                        'luminaireFrames' => fn ($query) => $this->tenants->scopeForUser($query->getQuery(), $user, LuminaireFrame::class)
                            ->with([
                                'frameType',
                                'luminaires' => fn ($query) => $this->tenants->scopeForUser($query->getQuery(), $user, Luminaire::class)
                                    ->with(['position', 'luminaireType'])
                                    ->orderBy('frame_position'),
                            ]),
                        'electricalBoards' => fn ($query) => $this->tenants->scopeForUser($query->getQuery(), $user, ElectricalBoard::class)
                            ->with('electricalBoardType'),
                    ]),
                'terrains.electricalBoards' => fn ($query) => $this->tenants->scopeForUser($query->getQuery(), $user, ElectricalBoard::class)
                    ->with('electricalBoardType'),
                'electricalBoards' => fn ($query) => $this->tenants->scopeForUser($query->getQuery(), $user, ElectricalBoard::class)
                    ->with('electricalBoardType'),
            ])
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $complexes->map(fn (Complex $complex): array => $this->complex($complex, $locale))->values(),
        ]);
    }

    private function complex(Complex $complex, string $locale): array
    {
        return [
            'id' => $complex->id,
            'name' => $complex->name,
            'address' => collect([$complex->street, $complex->zipcode, $complex->city])->filter()->join(', '),
            'location' => ['lat' => $complex->lat, 'lng' => $complex->lng, 'zoom' => $complex->zoom],
            'terrains' => $complex->terrains->map(fn (Terrain $terrain): array => $this->terrain($terrain, $locale))->values(),
            'electrical_boards' => $complex->electricalBoards->map(fn (ElectricalBoard $board): array => $this->board($board, $locale))->values(),
        ];
    }

    private function terrain(Terrain $terrain, string $locale): array
    {
        return [
            'id' => $terrain->id,
            'name' => $terrain->getTranslation('name', $locale, true),
            'type' => $terrain->terrainType?->getTranslation('type', $locale, true),
            'location' => ['lat' => $terrain->lat, 'lng' => $terrain->lng],
            'structures' => $terrain->structures->map(fn (Structure $structure): array => $this->structure($structure, $locale))->values(),
            'electrical_boards' => $terrain->electricalBoards->map(fn (ElectricalBoard $board): array => $this->board($board, $locale))->values(),
        ];
    }

    private function structure(Structure $structure, string $locale): array
    {
        return [
            'id' => $structure->id,
            'type' => $structure->structureType?->getTranslation('name', $locale, true),
            'location' => ['lat' => $structure->lat, 'lng' => $structure->lng],
            'frames' => $structure->luminaireFrames->map(fn (LuminaireFrame $frame): array => [
                'id' => $frame->id,
                'type' => $frame->frameType?->name,
                'positions' => $frame->luminaires->map(fn (Luminaire $luminaire): array => [
                    'id' => $luminaire->luminaire_position_id,
                    'position' => $luminaire->frame_position,
                    'x' => $luminaire->position?->frame_x ?? $luminaire->frame_x,
                    'y' => $luminaire->position?->frame_y ?? $luminaire->frame_y,
                    'luminaire_type' => $luminaire->luminaireType?->name,
                ])->values(),
            ])->values(),
            'electrical_boards' => $structure->electricalBoards->map(fn (ElectricalBoard $board): array => $this->board($board, $locale))->values(),
        ];
    }

    private function board(ElectricalBoard $board, string $locale): array
    {
        return [
            'id' => $board->id,
            'type' => $board->electricalBoardType?->getTranslation('name', $locale, true),
            'location_description' => $board->getTranslation('location_description', $locale, true),
            'location' => ['lat' => $board->lat, 'lng' => $board->lng],
        ];
    }
}
