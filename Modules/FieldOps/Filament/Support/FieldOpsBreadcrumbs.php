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
 * all hidden from the sidebar (shouldRegisterNavigation = false) *and* their
 * "type" breadcrumb segment (e.g. "Terrains") is intentionally not a link either —
 * a flat, unscoped index of every Terrain/Structure/Frame/Luminaire in the system
 * isn't a page this app wants reachable at all, sidebar or breadcrumb. Only
 * Complexes (still in the sidebar, a real browsable index) and the specific
 * record segments (e.g. "#6 — Hinged") stay clickable.
 */
class FieldOpsBreadcrumbs
{
    // Non-URL sentinel key for a breadcrumb "type" label that must render as
    // plain text, not a link — array keys can't be null (PHP coerces null to
    // '', which the blade can't tell apart from a real empty href), and every
    // link entry in this class is keyed by a real URL string, so a key that
    // can never look like one is enough to disambiguate. See
    // resources/views/vendor/filament/components/breadcrumbs.blade.php,
    // which treats any key starting with this prefix as non-clickable exactly
    // like Filament's own is_int($url) convention for its own "current page"
    // entry.
    private const UNLINKED = 'fieldops-breadcrumb-unlinked:';

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
            self::UNLINKED.'terrains' => TerrainResource::getBreadcrumb(),
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
            self::UNLINKED.'structures' => StructureResource::getBreadcrumb(),
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
            self::UNLINKED.'luminaire-frames' => LuminaireFrameResource::getBreadcrumb(),
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
            self::UNLINKED.'luminaires' => LuminaireResource::getBreadcrumb(),
        ];
    }
}
