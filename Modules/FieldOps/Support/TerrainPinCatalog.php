<?php

declare(strict_types=1);

namespace Modules\FieldOps\Support;

/**
 * Single source of truth for the fixed catalog of terrain-type marker icons
 * (CLA-256, expanded CLA-269). Each entry pairs a stable `code` (matched
 * against TerrainType::code) with a badge-style SVG template — the `${fill}`
 * placeholder is a JS template-literal token, resolved at runtime by the
 * Leaflet marker builder using the type's `pin_color`, not by PHP.
 *
 * Consumed from three places that must stay in sync only through this class:
 * - Filament pin selector (Catalogs/TerrainTypeResource form) — renders
 *   previews with `defaultColor` substituted in for `${fill}`.
 * - terrain-location-picker.blade.php — emits the JS switch/case via @foreach.
 * - map-panel.blade.php — same, for the read-only complex/terrain map.
 *
 * A `code` with no matching entry here (or no code at all) always falls back
 * to the generic teardrop pin, defined separately in both blade files.
 */
class TerrainPinCatalog
{
    /**
     * @return array<int, array{code: string, labelKey: string, defaultColor: string, svg: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'code' => 'soccer',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.soccer',
                'defaultColor' => '#4c8c4a',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <rect x="7" y="7" width="26" height="26" fill="none" stroke="white" stroke-width="2"/>
    <line x1="20" y1="7" x2="20" y2="33" stroke="white" stroke-width="2"/>
    <circle cx="20" cy="20" r="5" fill="none" stroke="white" stroke-width="2"/>
</svg>
SVG,
            ],
            [
                'code' => 'athletics',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.athletics',
                'defaultColor' => '#c0392b',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <ellipse cx="20" cy="20" rx="15" ry="12" fill="${fill}"/>
    <ellipse cx="20" cy="20" rx="12" ry="9" fill="none" stroke="white" stroke-width="2"/>
    <ellipse cx="20" cy="20" rx="9" ry="6" fill="none" stroke="white" stroke-width="2"/>
</svg>
SVG,
            ],
            [
                'code' => 'tennis',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.tennis',
                'defaultColor' => '#a7b23c',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <line x1="20" y1="7" x2="20" y2="33" stroke="white" stroke-width="2"/>
    <rect x="7" y="7" width="26" height="26" fill="none" stroke="white" stroke-width="2"/>
    <path d="M7,20 Q20,25 33,20 M7,20 Q20,15 33,20" fill="none" stroke="white" stroke-width="1"/>
</svg>
SVG,
            ],
            [
                'code' => 'padel',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.padel',
                'defaultColor' => '#2e9e8f',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <path d="M20,10 Q26,10 26,18 Q26,25 20,27 Q14,25 14,18 Q14,10 20,10 Z" fill="white"/>
    <circle cx="17.5" cy="15" r="1.1" fill="${fill}"/>
    <circle cx="22.5" cy="15" r="1.1" fill="${fill}"/>
    <circle cx="20" cy="19" r="1.1" fill="${fill}"/>
    <line x1="20" y1="27" x2="20" y2="32" stroke="white" stroke-width="2" stroke-linecap="round"/>
</svg>
SVG,
            ],
            [
                'code' => 'hockey',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.hockey',
                'defaultColor' => '#3f6fb0',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <path d="M17,10 L17,24 Q17,29 23,29" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round"/>
    <circle cx="26.5" cy="29" r="1.8" fill="white"/>
</svg>
SVG,
            ],
            [
                'code' => 'basketball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.basketball',
                'defaultColor' => '#d97a34',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <circle cx="20" cy="20" r="9" fill="none" stroke="white" stroke-width="1.6"/>
    <line x1="20" y1="11" x2="20" y2="29" stroke="white" stroke-width="1.4"/>
    <line x1="11" y1="20" x2="29" y2="20" stroke="white" stroke-width="1.4"/>
    <path d="M12.5,15 Q20,18.5 27.5,15" fill="none" stroke="white" stroke-width="1.4"/>
    <path d="M12.5,25 Q20,21.5 27.5,25" fill="none" stroke="white" stroke-width="1.4"/>
</svg>
SVG,
            ],
            [
                'code' => 'volleyball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.volleyball',
                'defaultColor' => '#d4a943',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <circle cx="20" cy="20" r="9" fill="none" stroke="white" stroke-width="1.6"/>
    <path d="M13,13 Q22,17 17,27" fill="none" stroke="white" stroke-width="1.3"/>
    <path d="M13,13 Q18,21 28,16" fill="none" stroke="white" stroke-width="1.3"/>
    <path d="M17,27 Q23,24 28,16" fill="none" stroke="white" stroke-width="1.3"/>
</svg>
SVG,
            ],
            [
                'code' => 'petanque',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.petanque',
                'defaultColor' => '#8a7458',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <circle cx="17" cy="22" r="5.2" fill="none" stroke="white" stroke-width="1.6"/>
    <circle cx="24.5" cy="17.5" r="5.2" fill="none" stroke="white" stroke-width="1.6"/>
    <circle cx="20.5" cy="26.5" r="3.8" fill="none" stroke="white" stroke-width="1.3"/>
    <circle cx="11.5" cy="12.5" r="1.7" fill="white"/>
</svg>
SVG,
            ],
            [
                'code' => 'multi_sport',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.multi_sport',
                'defaultColor' => '#6c5ba6',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <rect x="9" y="12" width="22" height="16" rx="1.5" fill="none" stroke="white" stroke-width="1.6"/>
    <line x1="20" y1="12" x2="20" y2="28" stroke="white" stroke-width="1.4"/>
    <circle cx="20" cy="20" r="3.4" fill="none" stroke="white" stroke-width="1.4"/>
</svg>
SVG,
            ],
            [
                'code' => 'korfball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.korfball',
                'defaultColor' => '#c9971f',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <circle cx="20" cy="15" r="6" fill="none" stroke="white" stroke-width="2"/>
    <line x1="20" y1="21" x2="20" y2="32" stroke="white" stroke-width="2" stroke-linecap="round"/>
    <line x1="14" y1="32" x2="26" y2="32" stroke="white" stroke-width="2" stroke-linecap="round"/>
</svg>
SVG,
            ],
            [
                'code' => 'rugby',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.rugby',
                'defaultColor' => '#7a5230',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <g transform="rotate(-25 20 20)">
        <ellipse cx="20" cy="20" rx="12" ry="7" fill="none" stroke="white" stroke-width="2"/>
        <line x1="9" y1="20" x2="31" y2="20" stroke="white" stroke-width="1.2"/>
    </g>
</svg>
SVG,
            ],
            [
                'code' => 'american_football',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.american_football',
                'defaultColor' => '#5c3a21',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <g transform="rotate(-25 20 20)">
        <ellipse cx="20" cy="20" rx="13" ry="6" fill="none" stroke="white" stroke-width="2"/>
        <line x1="14" y1="20" x2="26" y2="20" stroke="white" stroke-width="1.2"/>
        <line x1="16" y1="17.5" x2="16" y2="22.5" stroke="white" stroke-width="1"/>
        <line x1="19" y1="17.5" x2="19" y2="22.5" stroke="white" stroke-width="1"/>
        <line x1="22" y1="17.5" x2="22" y2="22.5" stroke="white" stroke-width="1"/>
        <line x1="25" y1="17.5" x2="25" y2="22.5" stroke="white" stroke-width="1"/>
    </g>
</svg>
SVG,
            ],
            [
                'code' => 'baseball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.baseball',
                'defaultColor' => '#8d6e4b',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <circle cx="20" cy="20" r="9" fill="none" stroke="white" stroke-width="1.8"/>
    <path d="M13,13 Q20,20 13,27" fill="none" stroke="white" stroke-width="1.3"/>
    <path d="M27,13 Q20,20 27,27" fill="none" stroke="white" stroke-width="1.3"/>
</svg>
SVG,
            ],
            [
                'code' => 'beach_volleyball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.beach_volleyball',
                'defaultColor' => '#e0b96d',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <circle cx="20" cy="16" r="7" fill="none" stroke="white" stroke-width="1.5"/>
    <path d="M15,11 Q21,15 17,22" fill="none" stroke="white" stroke-width="1"/>
    <path d="M25,11 Q19,15 23,22" fill="none" stroke="white" stroke-width="1"/>
    <path d="M8,29 Q14,26 20,29 Q26,32 32,29" fill="none" stroke="white" stroke-width="1.4" stroke-linecap="round"/>
</svg>
SVG,
            ],
            [
                'code' => 'golf',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.golf',
                'defaultColor' => '#1e5631',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <line x1="16" y1="10" x2="16" y2="30" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
    <path d="M16,10 L26,13.5 L16,17 Z" fill="white"/>
    <ellipse cx="20" cy="30" rx="10" ry="2.4" fill="none" stroke="white" stroke-width="1.4"/>
</svg>
SVG,
            ],
            [
                'code' => 'cycling_track',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.cycling_track',
                'defaultColor' => '#b5502f',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <circle cx="14" cy="25" r="6" fill="none" stroke="white" stroke-width="1.6"/>
    <circle cx="26" cy="25" r="6" fill="none" stroke="white" stroke-width="1.6"/>
    <path d="M14,25 L19,14 L26,25 M19,14 L23,14" fill="none" stroke="white" stroke-width="1.3" stroke-linejoin="round"/>
</svg>
SVG,
            ],
            [
                'code' => 'skatepark',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.skatepark',
                'defaultColor' => '#7f8c8d',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <path d="M9,28 Q9,14 24,12" fill="none" stroke="white" stroke-width="2" stroke-linecap="round"/>
    <line x1="9" y1="28" x2="31" y2="28" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
    <circle cx="27" cy="28" r="2" fill="white"/>
</svg>
SVG,
            ],
            [
                'code' => 'equestrian',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.equestrian',
                'defaultColor' => '#c2a878',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <path d="M13,28 L13,20 A7,7 0 0 1 27,20 L27,28" fill="none" stroke="white" stroke-width="2.4" stroke-linecap="round"/>
    <circle cx="13" cy="28" r="1.1" fill="white"/>
    <circle cx="27" cy="28" r="1.1" fill="white"/>
</svg>
SVG,
            ],
            [
                'code' => 'minigolf',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.minigolf',
                'defaultColor' => '#16a085',
                'svg' => <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <rect x="5" y="5" width="30" height="30" fill="${fill}" rx="4"/>
    <line x1="15" y1="10" x2="24" y2="27" stroke="white" stroke-width="1.8" stroke-linecap="round"/>
    <path d="M24,27 L29,25 L28,30 Z" fill="white"/>
    <circle cx="14" cy="30" r="2.4" fill="white"/>
</svg>
SVG,
            ],
        ];
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
