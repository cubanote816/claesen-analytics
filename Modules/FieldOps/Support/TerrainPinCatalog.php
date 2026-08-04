<?php

declare(strict_types=1);

namespace Modules\FieldOps\Support;

/**
 * Single source of truth for the fixed catalog of terrain-type marker icons
 * (CLA-256, expanded CLA-269, unified to the teardrop pin shape CLA-270).
 * Each entry pairs a stable `code` (matched against TerrainType::code) with a
 * teardrop-style SVG template — the `${fill}` placeholder is a JS
 * template-literal token, resolved at runtime by the Leaflet marker builder
 * using the type's `pin_color`, not by PHP.
 *
 * Every entry shares the same outer teardrop `<path>` (identical to the
 * generic fallback pin, defined separately in both blade files) so all
 * markers read as one visual family. The sport-specific detail is drawn
 * natively for this bulb (centered on (20,14), radius ~10) inside a
 * `<g transform="translate(20,14)">` group — these are the same icon
 * fragments already designed and reviewed in the `terrain-pins` artifact
 * (ccf2310c), ported here as-is rather than derived from the earlier
 * square-badge icons, which read poorly once squeezed into the narrower
 * teardrop head (CLA-270 fix — see handoff.md).
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
    private const string PIN_OUTLINE = <<<'SVG'
<path d="M20 4C14.48 4 10 8.48 10 14C10 21.5 20 36 20 36C20 36 30 21.5 30 14C30 8.48 25.52 4 20 4Z"
      fill="${fill}"
      stroke="white"
      stroke-width="1"/>
SVG;

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
                'svg' => self::pin(<<<'SVG'
<circle r="8" fill="none" stroke="#fff" stroke-width="1.6"/>
<polygon points="0,-3 2.85,-0.9 1.76,2.4 -1.76,2.4 -2.85,-0.9" fill="#fff"/>
<line x1="0" y1="-3" x2="0" y2="-8" stroke="#fff" stroke-width="1.3"/>
<line x1="2.85" y1="-0.9" x2="7.6" y2="-2.6" stroke="#fff" stroke-width="1.3"/>
<line x1="1.76" y1="2.4" x2="4.7" y2="6.7" stroke="#fff" stroke-width="1.3"/>
<line x1="-1.76" y1="2.4" x2="-4.7" y2="6.7" stroke="#fff" stroke-width="1.3"/>
<line x1="-2.85" y1="-0.9" x2="-7.6" y2="-2.6" stroke="#fff" stroke-width="1.3"/>
SVG),
            ],
            [
                'code' => 'athletics',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.athletics',
                'defaultColor' => '#c0392b',
                'svg' => self::pin(<<<'SVG'
<path d="M-1,-8.6 A8.6,8.6 0 0,1 -1,8.6" fill="none" stroke="#fff" stroke-width="1.3"/>
<path d="M-3.6,-9.8 A9.8,9.8 0 0,1 -3.6,9.8" fill="none" stroke="#fff" stroke-width="1.3"/>
<path d="M-6.2,-10.8 A10.8,10.8 0 0,1 -6.2,10.8" fill="none" stroke="#fff" stroke-width="1.3"/>
<line x1="-1" y1="-8.6" x2="-1" y2="8.6" stroke="#fff" stroke-width="1.1"/>
SVG),
            ],
            [
                'code' => 'tennis',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.tennis',
                'defaultColor' => '#a7b23c',
                'svg' => self::pin(<<<'SVG'
<ellipse cx="0" cy="-3.4" rx="5.6" ry="7.2" fill="none" stroke="#fff" stroke-width="1.5"/>
<line x1="-2.6" y1="-9.6" x2="-2.6" y2="2.8" stroke="#fff" stroke-width="0.9"/>
<line x1="0" y1="-10.4" x2="0" y2="3.4" stroke="#fff" stroke-width="0.9"/>
<line x1="2.6" y1="-9.6" x2="2.6" y2="2.8" stroke="#fff" stroke-width="0.9"/>
<line x1="-5.4" y1="-5.2" x2="5.4" y2="-5.2" stroke="#fff" stroke-width="0.9"/>
<line x1="-5.6" y1="-1.6" x2="5.6" y2="-1.6" stroke="#fff" stroke-width="0.9"/>
<line x1="0" y1="3.4" x2="0" y2="9.6" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
SVG),
            ],
            [
                'code' => 'padel',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.padel',
                'defaultColor' => '#2e9e8f',
                'svg' => self::pin(<<<'SVG'
<path d="M-5.2,-9.4 Q-6.6,-2 -3.2,3.2 Q0,5.4 3.2,3.2 Q6.6,-2 5.2,-9.4 Q0,-11.6 -5.2,-9.4 Z" fill="#fff"/>
<circle cx="-1.8" cy="-6.2" r="0.85" fill="${fill}"/>
<circle cx="1.8" cy="-6.2" r="0.85" fill="${fill}"/>
<circle cx="0" cy="-3" r="0.85" fill="${fill}"/>
<circle cx="-2.2" cy="0" r="0.85" fill="${fill}"/>
<circle cx="2.2" cy="0" r="0.85" fill="${fill}"/>
<line x1="0" y1="5.4" x2="0" y2="10.4" stroke="#fff" stroke-width="2" stroke-linecap="round"/>
SVG),
            ],
            [
                'code' => 'hockey',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.hockey',
                'defaultColor' => '#3f6fb0',
                'svg' => self::pin(<<<'SVG'
<path d="M-2.4,-9.4 L-2.4,4.2 Q-2.4,8.2 2.6,8.2" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
<circle cx="6.4" cy="8.2" r="1.7" fill="#fff"/>
SVG),
            ],
            [
                'code' => 'basketball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.basketball',
                'defaultColor' => '#d97a34',
                'svg' => self::pin(<<<'SVG'
<circle r="8" fill="none" stroke="#fff" stroke-width="1.6"/>
<line x1="0" y1="-8" x2="0" y2="8" stroke="#fff" stroke-width="1.3"/>
<line x1="-8" y1="0" x2="8" y2="0" stroke="#fff" stroke-width="1.3"/>
<path d="M-7.6,-3.6 Q0,-1.2 7.6,-3.6" fill="none" stroke="#fff" stroke-width="1.3"/>
<path d="M-7.6,3.6 Q0,1.2 7.6,3.6" fill="none" stroke="#fff" stroke-width="1.3"/>
SVG),
            ],
            [
                'code' => 'volleyball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.volleyball',
                'defaultColor' => '#d4a943',
                'svg' => self::pin(<<<'SVG'
<circle r="8" fill="none" stroke="#fff" stroke-width="1.6"/>
<path d="M-6.2,-6.2 Q2,-2 -2.4,6.2" fill="none" stroke="#fff" stroke-width="1.2"/>
<path d="M-6.2,-6.2 Q-1,1.2 7.2,-3" fill="none" stroke="#fff" stroke-width="1.2"/>
<path d="M-2.4,6.2 Q4,3 7.2,-3" fill="none" stroke="#fff" stroke-width="1.2"/>
SVG),
            ],
            [
                'code' => 'petanque',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.petanque',
                'defaultColor' => '#8a7458',
                'svg' => self::pin(<<<'SVG'
<circle cx="-3.2" cy="1.2" r="3.5" fill="none" stroke="#fff" stroke-width="1.4"/>
<circle cx="3.4" cy="-2" r="3.5" fill="none" stroke="#fff" stroke-width="1.4"/>
<circle cx="0.8" cy="4.4" r="2.6" fill="none" stroke="#fff" stroke-width="1.2"/>
<circle cx="-6.6" cy="-4.4" r="1.15" fill="#fff"/>
SVG),
            ],
            [
                'code' => 'multi_sport',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.multi_sport',
                'defaultColor' => '#6c5ba6',
                'svg' => self::pin(<<<'SVG'
<rect x="-8" y="-6" width="16" height="12" rx="1.2" fill="none" stroke="#fff" stroke-width="1.4"/>
<line x1="0" y1="-6" x2="0" y2="6" stroke="#fff" stroke-width="1.2"/>
<circle cx="0" cy="0" r="2.6" fill="none" stroke="#fff" stroke-width="1.2"/>
SVG),
            ],
            [
                'code' => 'korfball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.korfball',
                'defaultColor' => '#c9971f',
                'svg' => self::pin(<<<'SVG'
<circle cy="-2" r="4.4" fill="none" stroke="#fff" stroke-width="1.3"/>
<line x1="0" y1="2.4" x2="0" y2="8.6" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/>
<line x1="-3" y1="8.6" x2="3" y2="8.6" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/>
SVG),
            ],
            [
                'code' => 'rugby',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.rugby',
                'defaultColor' => '#7a5230',
                'svg' => self::pin(<<<'SVG'
<ellipse cx="0" cy="0" rx="8.4" ry="4.6" fill="none" stroke="#fff" stroke-width="1.4" transform="rotate(-25)"/>
<line x1="-6.4" y1="0" x2="6.4" y2="0" stroke="#fff" stroke-width="1" transform="rotate(-25)"/>
SVG),
            ],
            [
                'code' => 'american_football',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.american_football',
                'defaultColor' => '#5c3a21',
                'svg' => self::pin(<<<'SVG'
<ellipse cx="0" cy="0" rx="8.8" ry="4" fill="none" stroke="#fff" stroke-width="1.4" transform="rotate(-25)"/>
<g transform="rotate(-25)">
    <line x1="-6" y1="0" x2="6" y2="0" stroke="#fff" stroke-width="0.9"/>
    <line x1="-3" y1="-1.6" x2="-3" y2="1.6" stroke="#fff" stroke-width="0.8"/>
    <line x1="0" y1="-1.6" x2="0" y2="1.6" stroke="#fff" stroke-width="0.8"/>
    <line x1="3" y1="-1.6" x2="3" y2="1.6" stroke="#fff" stroke-width="0.8"/>
</g>
SVG),
            ],
            [
                'code' => 'baseball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.baseball',
                'defaultColor' => '#8d6e4b',
                'svg' => self::pin(<<<'SVG'
<circle r="8" fill="none" stroke="#fff" stroke-width="1.5"/>
<path d="M-4,-6.6 Q2,0 -4,6.6" fill="none" stroke="#fff" stroke-width="1.1"/>
<path d="M4,-6.6 Q-2,0 4,6.6" fill="none" stroke="#fff" stroke-width="1.1"/>
SVG),
            ],
            [
                'code' => 'beach_volleyball',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.beach_volleyball',
                'defaultColor' => '#e0b96d',
                'svg' => self::pin(<<<'SVG'
<circle cy="-2.4" r="5.6" fill="none" stroke="#fff" stroke-width="1.3"/>
<path d="M-3.8,-6 Q1.4,-2.4 -1.6,3.4" fill="none" stroke="#fff" stroke-width="0.9"/>
<path d="M4.2,-6 Q-0.8,-2.4 2.4,3.4" fill="none" stroke="#fff" stroke-width="0.9"/>
<path d="M-7.4,7.6 Q-2.4,5 2,7.6 Q6.4,10 9.4,7.6" fill="none" stroke="#fff" stroke-width="1.2" stroke-linecap="round"/>
SVG),
            ],
            [
                'code' => 'golf',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.golf',
                'defaultColor' => '#1e5631',
                'svg' => self::pin(<<<'SVG'
<line x1="-2.6" y1="-8.6" x2="-2.6" y2="7.6" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/>
<path d="M-2.6,-8.6 L5.4,-5.4 L-2.6,-2.2 Z" fill="#fff"/>
<ellipse cy="7.6" rx="7.4" ry="1.7" fill="none" stroke="#fff" stroke-width="1.1"/>
SVG),
            ],
            [
                'code' => 'cycling_track',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.cycling_track',
                'defaultColor' => '#b5502f',
                'svg' => self::pin(<<<'SVG'
<circle cx="-4.4" cy="4.4" r="4.2" fill="none" stroke="#fff" stroke-width="1.2"/>
<circle cx="4.4" cy="4.4" r="4.2" fill="none" stroke="#fff" stroke-width="1.2"/>
<path d="M-4.4,4.4 L0,-4.6 L4.4,4.4 M0,-4.6 L2.6,-4.6" fill="none" stroke="#fff" stroke-width="1" stroke-linejoin="round"/>
SVG),
            ],
            [
                'code' => 'skatepark',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.skatepark',
                'defaultColor' => '#7f8c8d',
                'svg' => self::pin(<<<'SVG'
<path d="M-7.6,7.4 Q-7.6,-3.6 5.4,-5.4" fill="none" stroke="#fff" stroke-width="1.4" stroke-linecap="round"/>
<line x1="-7.6" y1="7.4" x2="7.6" y2="7.4" stroke="#fff" stroke-width="1.1" stroke-linecap="round"/>
<circle cx="4.4" cy="7.4" r="1.4" fill="#fff"/>
SVG),
            ],
            [
                'code' => 'equestrian',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.equestrian',
                'defaultColor' => '#c2a878',
                'svg' => self::pin(<<<'SVG'
<path d="M-4.4,7.4 L-4.4,1.4 A4.4,4.4 0 0,1 4.4,1.4 L4.4,7.4" fill="none" stroke="#fff" stroke-width="1.7" stroke-linecap="round"/>
<circle cx="-4.4" cy="7.4" r="0.9" fill="#fff"/>
<circle cx="4.4" cy="7.4" r="0.9" fill="#fff"/>
SVG),
            ],
            [
                'code' => 'minigolf',
                'labelKey' => 'fieldops::resource.catalogs.pin_catalog.minigolf',
                'defaultColor' => '#16a085',
                'svg' => self::pin(<<<'SVG'
<line x1="-4.4" y1="-8" x2="2.8" y2="6.6" stroke="#fff" stroke-width="1.3" stroke-linecap="round"/>
<path d="M2.8,6.6 L7,5 L6,9.4 Z" fill="#fff"/>
<circle cx="-5.4" cy="8.4" r="1.7" fill="#fff"/>
SVG),
            ],
        ];
    }

    /**
     * Wraps a sport's icon fragment (drawn origin-centered, matching the
     * `terrain-pins` artifact's coordinate space) with the shared teardrop
     * outline, positioning the group at the bulb's center (20,14).
     */
    private static function pin(string $detail): string
    {
        $outline = trim(self::PIN_OUTLINE);
        $detail = trim($detail);

        return <<<SVG
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    {$outline}
    <g transform="translate(20,14)">
        {$detail}
    </g>
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

    /**
     * Returns a render-ready SVG for an API consumer. This keeps external maps
     * on the exact same marker geometry as the FieldOps backoffice rather than
     * making each client reimplement a parallel icon switch.
     */
    public static function svg(?string $code, ?string $color): string
    {
        $fill = $color ?: '#e6007e';
        $template = self::find($code)['svg'] ?? <<<SVG
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <path d="M20 4C14.48 4 10 8.48 10 14C10 21.5 20 36 20 36C20 36 30 21.5 30 14C30 8.48 25.52 4 20 4Z" fill="\${fill}" stroke="white" stroke-width="1"/>
    <line x1="16.5" y1="10" x2="16.5" y2="21" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
    <path d="M16.5,10 L23,12.7 L16.5,15.4 Z" fill="white"/>
</svg>
SVG;

        return str_replace('${fill}', $fill, $template);
    }
}
