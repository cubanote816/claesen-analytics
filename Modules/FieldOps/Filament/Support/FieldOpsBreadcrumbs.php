<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Support;

use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Resources\TerrainResource;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;

/**
 * Builds the ancestor chain for CLA-278's hierarchical breadcrumbs
 * (Complexes > {complex} > Terrains > {terrain} > ...). Filament's native
 * getParentResourceRegistration() mechanism assumes a single parent per record
 * (it reassigns $parentRecord = $parentRecord->{inverseRelationship} in a loop),
 * which doesn't hold here: Structure<->Terrain and LuminaireFrame<->Structure are
 * real M:N in production data (confirmed — not just schema-possible), so a record
 * can have more than one valid parent. Each level accepts an optional "via" id
 * (the parent the user actually navigated through) and falls back to
 * Structure::resolveTerrain()/LuminaireFrame::resolveStructure()'s deterministic
 * default (lowest id) when absent.
 *
 * Each level has two methods:
 * - `xTrail()` returns the chain INCLUDING that record's own linked entry — used
 *   when that record is an ancestor of a *deeper* current page.
 * - `xAncestors()` returns the chain EXCLUDING that record's own entry — used as
 *   the getResourceBreadcrumbs() override on that record's *own* View/Edit page,
 *   since Filament's base getBreadcrumbs() (InteractsWithRecord) already appends
 *   the current record's own linked title+the final page label automatically.
 *   Mixing these up produces a duplicated last-but-one breadcrumb entry.
 *
 * TerrainResource/StructureResource/LuminaireFrameResource/LuminaireResource are
 * all hidden from the sidebar (shouldRegisterNavigation = false) — the "type"
 * segment (e.g. "Terrains") still links to that resource's own index route, which
 * still exists, just isn't linked from the sidebar.
 */
class FieldOpsBreadcrumbs
{
    /** @return array<string, string> */
    public static function complexTrail(Complex $complex): array
    {
        return [
            ComplexResource::getUrl() => ComplexResource::getBreadcrumb(),
            ComplexResource::getUrl('view', ['record' => $complex]) => (string) $complex->name,
        ];
    }

    /** @return array<string, string> */
    public static function terrainAncestors(Terrain $terrain): array
    {
        return [
            ...($terrain->complex ? static::complexTrail($terrain->complex) : []),
            TerrainResource::getUrl() => TerrainResource::getBreadcrumb(),
        ];
    }

    /** @return array<string, string> */
    public static function terrainTrail(Terrain $terrain): array
    {
        return [
            ...static::terrainAncestors($terrain),
            TerrainResource::getUrl('view', ['record' => $terrain]) => (string) $terrain->name,
        ];
    }

    /** @return array<string, string> */
    public static function structureAncestors(Structure $structure, ?int $viaTerrainId = null): array
    {
        $terrain = $structure->resolveTerrain($viaTerrainId);

        return [
            ...($terrain ? static::terrainTrail($terrain) : []),
            StructureResource::getUrl() => StructureResource::getBreadcrumb(),
        ];
    }

    /** @return array<string, string> */
    public static function structureTrail(Structure $structure, ?int $viaTerrainId = null): array
    {
        $terrain = $structure->resolveTerrain($viaTerrainId);

        return [
            ...static::structureAncestors($structure, $viaTerrainId),
            StructureResource::getUrl('view', array_filter([
                'record' => $structure,
                'via_terrain' => $terrain?->id,
            ])) => StructureResource::getRecordTitle($structure),
        ];
    }

    /** @return array<string, string> */
    public static function luminaireFrameAncestors(LuminaireFrame $frame, ?int $viaStructureId = null, ?int $viaTerrainId = null): array
    {
        $structure = $frame->resolveStructure($viaStructureId);

        return [
            ...($structure ? static::structureTrail($structure, $viaTerrainId) : []),
            LuminaireFrameResource::getUrl() => LuminaireFrameResource::getBreadcrumb(),
        ];
    }

    /** @return array<string, string> */
    public static function luminaireFrameTrail(LuminaireFrame $frame, ?int $viaStructureId = null, ?int $viaTerrainId = null): array
    {
        $structure = $frame->resolveStructure($viaStructureId);
        $terrain = $structure?->resolveTerrain($viaTerrainId);

        return [
            ...static::luminaireFrameAncestors($frame, $viaStructureId, $viaTerrainId),
            LuminaireFrameResource::getUrl('view', array_filter([
                'record' => $frame,
                'via_structure' => $structure?->id,
                'via_terrain' => $terrain?->id,
            ])) => LuminaireFrameResource::getRecordTitle($frame),
        ];
    }

    /** @return array<string, string> */
    public static function luminaireAncestors(Luminaire $luminaire, ?int $viaStructureId = null, ?int $viaTerrainId = null): array
    {
        $frame = $luminaire->luminaireFrame;

        return [
            ...($frame ? static::luminaireFrameTrail($frame, $viaStructureId, $viaTerrainId) : []),
            LuminaireResource::getUrl() => LuminaireResource::getBreadcrumb(),
        ];
    }
}
