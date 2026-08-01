<?php

declare(strict_types=1);

namespace Modules\FieldOps\Filament\Support;

use Modules\FieldOps\Filament\Resources\ComplexResource;
use Modules\FieldOps\Filament\Resources\ElectricalBoardResource;
use Modules\FieldOps\Filament\Resources\FoMaintenanceWorkOrderResource;
use Modules\FieldOps\Filament\Resources\LuminaireFrameResource;
use Modules\FieldOps\Filament\Resources\LuminaireResource;
use Modules\FieldOps\Filament\Resources\StructureResource;
use Modules\FieldOps\Filament\Resources\TerrainResource;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
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
        return static::terrainAncestorsForComplex($terrain->complex);
    }

    // Same chain as terrainAncestors(), but for a Create page — there's no
    // Terrain record yet, only the Complex it's being created under (query
    // param the "Create terrain" action already passes).
    /** @return array<string, string> */
    public static function terrainAncestorsForComplex(?Complex $complex): array
    {
        return [
            ...($complex ? static::complexTrail($complex) : []),
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
        return static::structureAncestorsForTerrain($structure->resolveTerrain($viaTerrainId));
    }

    // Same chain as structureAncestors(), but for a Create page — no Structure
    // record yet, only the Terrain it's being created under.
    /** @return array<string, string> */
    public static function structureAncestorsForTerrain(?Terrain $terrain): array
    {
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
        return static::luminaireFrameAncestorsForStructure($frame->resolveStructure($viaStructureId), $viaTerrainId);
    }

    // Same chain as luminaireFrameAncestors(), but for a Create page — no
    // LuminaireFrame record yet, only the Structure it's being created under
    // (structure_ids query param LuminaireFramesRelationManager's "Create"
    // action already sends).
    /** @return array<string, string> */
    public static function luminaireFrameAncestorsForStructure(?Structure $structure, ?int $viaTerrainId = null): array
    {
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

    // Same shape as luminaireAncestors(), but for a Create page — no Luminaire
    // (and usually no Frame either, that's picked inside the form itself) yet.
    // Only useful if the Create action is ever reached with via_structure/
    // via_terrain context; no caller does today (Create Luminaire's only
    // wired entry point is a modal reusing this same form, not this page),
    // kept for whenever one does — falls back to a bare "Luminaires" label
    // otherwise, same as every other case where context is unavailable.
    /** @return array<string, string> */
    public static function luminaireAncestorsForStructure(?Structure $structure, ?int $viaTerrainId = null): array
    {
        return [
            ...($structure ? static::structureTrail($structure, $viaTerrainId) : []),
            self::UNLINKED.'luminaires' => LuminaireResource::getBreadcrumb(),
        ];
    }

    /** @return array<string, string> */
    public static function luminaireTrail(Luminaire $luminaire, ?int $viaStructureId = null, ?int $viaTerrainId = null): array
    {
        return [
            ...static::luminaireAncestors($luminaire, $viaStructureId, $viaTerrainId),
            LuminaireResource::getUrl('view', array_filter([
                'record' => $luminaire,
                'via_structure' => $viaStructureId,
                'via_terrain' => $viaTerrainId,
            ])) => LuminaireResource::getRecordTitle($luminaire),
        ];
    }

    /**
     * ElectricalBoard has no single canonical parent once it exists — it can
     * belong to several complexes/terrains/structures at once via its 3
     * pivots, so unlike Terrain/Structure/LuminaireFrame/Luminaire it never
     * gets a fixed place in the Complex→Terrain→Structure chain (its own
     * index stays the permanent anchor: see the ElectricalBoards::getUrl()
     * fallback below, and maintenanceWorkOrderAncestors()). But at any GIVEN
     * moment — creating one, or viewing/editing one reached by clicking a row
     * on a specific Complex/Terrain/Structure's "Electrical boards" tab —
     * there IS exactly one concrete entry point, and that specific chain is
     * real and worth showing. Used by Create (structure_ids/terrain_ids/
     * complex_id query params) and View/Edit (via_structure/via_terrain/
     * via_complex, forwarded by each of the 3 ElectricalBoardsRelationManager
     * variants' recordUrl()) alike — deepest known context wins, and it's
     * fine for this to resolve to nothing at all when reached from the flat
     * "Electrical boards" sidebar index, where there genuinely is no parent
     * context to show.
     *
     * @return array<string, string>
     */
    public static function electricalBoardAncestors(?Structure $structure, ?Terrain $terrain, ?Complex $complex, ?int $viaTerrainId = null): array
    {
        $trail = match (true) {
            $structure !== null => static::structureTrail($structure, $viaTerrainId),
            $terrain !== null => static::terrainTrail($terrain),
            $complex !== null => static::complexTrail($complex),
            default => [],
        };

        return [
            ...$trail,
            ElectricalBoardResource::getUrl() => ElectricalBoardResource::getBreadcrumb(),
        ];
    }

    // Same chain as electricalBoardAncestors(), plus the board's own linked
    // entry — used wherever an ElectricalBoard is an ancestor of a *deeper*
    // current page (maintenanceWorkOrderAncestors() below), same xTrail()
    // vs xAncestors() split as every other level in this class.
    /** @return array<string, string> */
    public static function electricalBoardTrail(ElectricalBoard $board, ?Structure $structure, ?Terrain $terrain, ?Complex $complex, ?int $viaTerrainId = null): array
    {
        return [
            ...static::electricalBoardAncestors($structure, $terrain, $complex, $viaTerrainId),
            ElectricalBoardResource::getUrl('view', array_filter([
                'record' => $board,
                'via_structure' => $structure?->id,
                'via_terrain' => $terrain?->id ?? $viaTerrainId,
                'via_complex' => $complex?->id,
            ])) => ElectricalBoardResource::getRecordTitle($board),
        ];
    }

    /**
     * Maintenance work orders aren't part of the Complex→Terrain→Structure→Frame
     * hierarchy — they hang off a Luminaire or an ElectricalBoard directly (see
     * MaintenanceEquipmentContextService). ElectricalBoard isn't hidden from the
     * sidebar like the other 4 leaf resources (it can belong to several
     * complexes/terrains/structures via its own pivots, so it doesn't have "a"
     * place in that chain either) — its own index stays a real link here, same
     * as Complexes.
     *
     * @return array<string, string>
     */
    public static function maintenanceWorkOrderAncestors(string $maintainableType, int|string $maintainableId, ?int $viaStructureId = null, ?int $viaTerrainId = null, ?int $viaComplexId = null): array
    {
        $trail = match ($maintainableType) {
            Luminaire::class => ($luminaire = Luminaire::find($maintainableId))
                ? static::luminaireTrail($luminaire, $viaStructureId, $viaTerrainId)
                : [],
            ElectricalBoard::class => ($board = ElectricalBoard::find($maintainableId))
                ? static::electricalBoardTrail(
                    $board,
                    $viaStructureId ? Structure::find($viaStructureId) : null,
                    $viaTerrainId ? Terrain::find($viaTerrainId) : null,
                    $viaComplexId ? Complex::find($viaComplexId) : null,
                    $viaTerrainId,
                )
                : [],
            default => [],
        };

        return [
            ...$trail,
            FoMaintenanceWorkOrderResource::getUrl() => FoMaintenanceWorkOrderResource::getBreadcrumb(),
        ];
    }
}
