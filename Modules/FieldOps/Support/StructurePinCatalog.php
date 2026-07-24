<?php

declare(strict_types=1);

namespace Modules\FieldOps\Support;

/**
 * Single source of truth for the fixed catalog of structure-type marker icons
 * (CLA-277). Unlike TerrainPinCatalog's teardrop family, these are full-scene
 * silhouettes on a transparent canvas (viewBox 0 0 160 230) — a mast/roofline,
 * a floodlight cluster, and a light beam — with the anchor point at the base
 * of the mast (80, 224), not a bulb center. Colors are fixed (a "night photo"
 * amber + a drop-shadow under the light structural shapes), not tinted per
 * record: `pin_color` on StructureType stays cosmetic, used only for the
 * Filament table badge — see StructureTypeResource.
 *
 * Every shape here is solid fill, not a thin stroke: the map renders these at
 * 40×57 CSS px (iconSize in map-panel.blade.php), a 4x scale-down from the
 * 160×230 viewBox. A first pass used ~2px strokes for the mast/bracket, which
 * is sub-pixel at render size and reads as an inconsistent, near-invisible
 * hairline against busy satellite tiles — confirmed with a real Playwright
 * screenshot at map scale, not just the large artifact preview (same lesson
 * as [[feedback_visual_verification]]: a clean render at 200px does not
 * predict legibility at 40px). Every structural piece here is therefore a
 * filled rect/circle sized to stay clearly visible once scaled down, and a
 * single feDropShadow filter (not a hand-drawn dark halo line, which had the
 * same sub-pixel problem) keeps it readable against both light and dark
 * basemap tiles.
 *
 * Every StructureType without a matching code (including "Other") falls back
 * to a deliberately plain single-light icon, hardcoded in map-panel.blade.php
 * the same way TerrainPinCatalog's generic teardrop fallback is — not part of
 * definitions() here, so it never needs a StructureType row to exist.
 *
 * Consumed from:
 * - Filament pin selector (Catalogs/StructureTypeResource form).
 * - map-panel.blade.php (read-only complex/structure map).
 */
class StructurePinCatalog
{
    private const string BEAM_GRADIENT = <<<'SVG'
<linearGradient id="structurePinBeam" x1="50%" y1="0%" x2="50%" y2="100%">
    <stop offset="0%" stop-color="#ffcf6b" stop-opacity="0.75"/>
    <stop offset="100%" stop-color="#ffcf6b" stop-opacity="0"/>
</linearGradient>
SVG;

    private const string CLUSTER = <<<'SVG'
<g filter="url(#structurePinGlow)" fill="#ffd479">
    <rect x="26" y="10" width="34" height="21" rx="4"/>
    <rect x="63" y="4" width="34" height="21" rx="4"/>
    <rect x="99" y="10" width="33" height="21" rx="4"/>
    <rect x="45" y="28" width="33" height="21" rx="4"/>
    <rect x="82" y="28" width="33" height="21" rx="4"/>
</g>
SVG;

    private const string GLOW_FILTER = <<<'SVG'
<filter id="structurePinGlow" x="-60%" y="-60%" width="220%" height="220%">
    <feGaussianBlur stdDeviation="2.4" result="blur"/>
    <feMerge>
        <feMergeNode in="blur"/>
        <feMergeNode in="SourceGraphic"/>
    </feMerge>
</filter>
SVG;

    private const string SHADOW_FILTER = <<<'SVG'
<filter id="structurePinShadow" x="-60%" y="-60%" width="220%" height="220%">
    <feDropShadow dx="0" dy="1.5" stdDeviation="2" flood-color="#02060e" flood-opacity="0.7"/>
</filter>
SVG;

    /**
     * @return array<int, array{code: string, labelKey: string, defaultColor: string, svg: string}>
     */
    public static function definitions(): array
    {
        $conicalMastAndBeam = <<<'SVG'
<polygon points="80,68 30,150 130,150" fill="url(#structurePinBeam)" opacity="0.55"/>
<polygon points="80,68 58,150 102,150" fill="url(#structurePinBeam)" opacity="0.8"/>
<g filter="url(#structurePinShadow)" fill="#eef2f8">
    <rect x="38" y="50" width="84" height="14" rx="6"/>
    <rect x="73" y="60" width="14" height="164" rx="6"/>
</g>
SVG;

        $hingedMastAndBeam = <<<'SVG'
<polygon points="80,68 30,150 130,150" fill="url(#structurePinBeam)" opacity="0.55"/>
<polygon points="80,68 58,150 102,150" fill="url(#structurePinBeam)" opacity="0.8"/>
<g filter="url(#structurePinShadow)" fill="#eef2f8">
    <rect x="38" y="50" width="84" height="14" rx="6"/>
    <rect x="73" y="60" width="14" height="76" rx="6"/>
    <rect x="73" y="164" width="14" height="60" rx="6"/>
</g>
<g filter="url(#structurePinShadow)">
    <circle cx="80" cy="150" r="16" fill="#0b1526"/>
    <circle cx="80" cy="150" r="16" fill="none" stroke="#eef2f8" stroke-width="4"/>
    <circle cx="80" cy="150" r="4.5" fill="#eef2f8"/>
</g>
SVG;

        $roofBarAndBeam = <<<'SVG'
<polygon points="80,92 34,166 126,166" fill="url(#structurePinBeam)" opacity="0.5"/>
<polygon points="80,92 60,166 100,166" fill="url(#structurePinBeam)" opacity="0.78"/>
<g filter="url(#structurePinShadow)" fill="#eef2f8">
    <rect x="22" y="52" width="116" height="14" rx="6"/>
    <rect x="73" y="66" width="14" height="20" rx="5"/>
</g>
SVG;

        return [
            [
                'code' => 'conical',
                'labelKey' => 'fieldops::resource.catalogs.structure_pin_catalog.conical',
                'defaultColor' => '#f5a524',
                'svg' => self::wrap($conicalMastAndBeam.self::CLUSTER),
            ],
            [
                'code' => 'hinged',
                'labelKey' => 'fieldops::resource.catalogs.structure_pin_catalog.hinged',
                'defaultColor' => '#f5a524',
                'svg' => self::wrap($hingedMastAndBeam.self::CLUSTER),
            ],
            [
                'code' => 'roof',
                'labelKey' => 'fieldops::resource.catalogs.structure_pin_catalog.roof',
                'defaultColor' => '#f5a524',
                'svg' => self::wrap($roofBarAndBeam.self::smallCluster()),
            ],
        ];
    }

    /**
     * A tighter 3-piece version of CLUSTER for the roof mount, whose bar
     * leaves less vertical room above the beam than the mast-top types.
     */
    private static function smallCluster(): string
    {
        return <<<'SVG'
<g filter="url(#structurePinGlow)" fill="#ffd479">
    <rect x="58" y="86" width="26" height="20" rx="4"/>
    <rect x="88" y="86" width="26" height="20" rx="4"/>
    <rect x="73" y="70" width="26" height="20" rx="4"/>
</g>
SVG;
    }

    /**
     * The deliberately plain fallback icon — used for "Other" and any
     * structure type without a matching code. Mirrors the always-null
     * "Generic" pin in TerrainPinCatalog, but drawn in this catalog's own
     * silhouette style instead of the teardrop.
     */
    public static function fallbackSvg(): string
    {
        return self::wrap(<<<'SVG'
<polygon points="80,178 50,224 110,224" fill="url(#structurePinBeam)" opacity="0.6"/>
<g filter="url(#structurePinShadow)" fill="#eef2f8">
    <rect x="73" y="188" width="14" height="36" rx="5"/>
</g>
<g filter="url(#structurePinGlow)" fill="#ffd479">
    <rect x="60" y="168" width="40" height="24" rx="5"/>
</g>
SVG);
    }

    private static function wrap(string $content): string
    {
        $defs = trim(self::GLOW_FILTER)."\n".trim(self::SHADOW_FILTER)."\n".trim(self::BEAM_GRADIENT);
        $content = trim($content);

        return <<<SVG
<svg viewBox="0 0 160 230" xmlns="http://www.w3.org/2000/svg">
    <defs>
        {$defs}
    </defs>
    {$content}
</svg>
SVG;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_column(self::definitions(), 'code');
    }

    /**
     * @return array{code: string, labelKey: string, defaultColor: string, svg: string}|null
     */
    public static function find(?string $code): ?array
    {
        if (! $code) {
            return null;
        }

        foreach (self::definitions() as $pin) {
            if ($pin['code'] === $code) {
                return $pin;
            }
        }

        return null;
    }
}
