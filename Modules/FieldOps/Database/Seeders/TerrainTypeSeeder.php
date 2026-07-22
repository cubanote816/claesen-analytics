<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\TerrainType;

// Ported from the deprecated satellite (api-claesen-sport-app) — original data
// also had an 'es' locale, dropped per FO-008 (canonical FieldOps locales are
// nl/en/fr/de). 'de' values are new, not present in the old seeder. Note the
// translatable attribute here is 'type', not 'name' (see TerrainType model).
//
// `code` + `pin_color` (CLA-256) are the single source of truth for the Leaflet
// pin rendered per terrain type — Filament (TerrainResource::resolveTerrainPinVariants())
// and the Claesen-Sport frontend both key off `code` and read `pin_color` instead of
// hardcoding their own palettes. Colors are drawn from each sport's real surface
// (grass, Belgian blue hockey turf, athletics track red, etc.), approved in the pin
// design review 2026-07-11/12.
class TerrainTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'soccer', 'pin_color' => '#4c8c4a', 'en' => 'Soccer', 'nl' => 'Voetbal', 'fr' => 'Football', 'de' => 'Fußball'],
            ['code' => 'tennis', 'pin_color' => '#a7b23c', 'en' => 'Tennis', 'nl' => 'Tennis', 'fr' => 'Tennis', 'de' => 'Tennis'],
            ['code' => 'athletics', 'pin_color' => '#c0392b', 'en' => 'Athletics', 'nl' => 'Atletiek', 'fr' => 'Athlétisme', 'de' => 'Leichtathletik'],
            ['code' => 'padel', 'pin_color' => '#2e9e8f', 'en' => 'Padel', 'nl' => 'Padel', 'fr' => 'Padel', 'de' => 'Padel'],
            ['code' => 'hockey', 'pin_color' => '#3f6fb0', 'en' => 'Field hockey', 'nl' => 'Hockey', 'fr' => 'Hockey', 'de' => 'Hockey'],
            ['code' => 'basketball', 'pin_color' => '#d97a34', 'en' => 'Basketball', 'nl' => 'Basketbal', 'fr' => 'Basketball', 'de' => 'Basketball'],
            ['code' => 'volleyball', 'pin_color' => '#d4a943', 'en' => 'Volleyball', 'nl' => 'Volleybal', 'fr' => 'Volleyball', 'de' => 'Volleyball'],
            ['code' => 'petanque', 'pin_color' => '#8a7458', 'en' => 'Pétanque', 'nl' => 'Petanque', 'fr' => 'Pétanque', 'de' => 'Pétanque'],
            ['code' => 'multi_sport', 'pin_color' => '#6c5ba6', 'en' => 'Multi-sport court', 'nl' => 'Multifunctioneel terrein', 'fr' => 'Terrain multisport', 'de' => 'Mehrzweckplatz'],
            // CLA-269 — catalog expanded from 9 to 19 codes, covering realistic
            // outdoor sports facilities across Belgium/Netherlands/Germany
            // (Claesen's service region), approved 2026-07-22.
            ['code' => 'korfball', 'pin_color' => '#c9971f', 'en' => 'Korfball', 'nl' => 'Korfbal', 'fr' => 'Korfball', 'de' => 'Korbball'],
            ['code' => 'rugby', 'pin_color' => '#7a5230', 'en' => 'Rugby', 'nl' => 'Rugby', 'fr' => 'Rugby', 'de' => 'Rugby'],
            ['code' => 'american_football', 'pin_color' => '#5c3a21', 'en' => 'American football', 'nl' => 'American football', 'fr' => 'Football américain', 'de' => 'American Football'],
            ['code' => 'baseball', 'pin_color' => '#8d6e4b', 'en' => 'Baseball', 'nl' => 'Honkbal', 'fr' => 'Baseball', 'de' => 'Baseball'],
            ['code' => 'beach_volleyball', 'pin_color' => '#e0b96d', 'en' => 'Beach volleyball', 'nl' => 'Beachvolleybal', 'fr' => 'Beach-volley', 'de' => 'Beachvolleyball'],
            ['code' => 'golf', 'pin_color' => '#1e5631', 'en' => 'Golf (driving range)', 'nl' => 'Golf (driving range)', 'fr' => 'Golf (practice)', 'de' => 'Golf (Übungsanlage)'],
            ['code' => 'cycling_track', 'pin_color' => '#b5502f', 'en' => 'Cycling track (velodrome)', 'nl' => 'Wielerbaan', 'fr' => 'Vélodrome', 'de' => 'Radrennbahn'],
            ['code' => 'skatepark', 'pin_color' => '#7f8c8d', 'en' => 'Skatepark', 'nl' => 'Skatepark', 'fr' => 'Skatepark', 'de' => 'Skatepark'],
            ['code' => 'equestrian', 'pin_color' => '#c2a878', 'en' => 'Equestrian arena', 'nl' => 'Buitenrijbaan', 'fr' => 'Manège extérieur', 'de' => 'Reitplatz'],
            ['code' => 'minigolf', 'pin_color' => '#16a085', 'en' => 'Minigolf', 'nl' => 'Minigolf', 'fr' => 'Minigolf', 'de' => 'Minigolf'],
        ];

        foreach ($types as $type) {
            $translations = [
                'en' => $type['en'],
                'nl' => $type['nl'],
                'fr' => $type['fr'],
                'de' => $type['de'],
            ];

            // Backfill existing rows from the original (pre-CLA-256) seeder run, which had
            // no `code` yet and matched only on the translated 'en' value.
            $existing = TerrainType::query()->where('code', $type['code'])->first()
                ?? TerrainType::query()->whereNull('code')->where('type->en', $type['en'])->first();

            if ($existing) {
                $existing->fill(['code' => $type['code'], 'pin_color' => $type['pin_color']])->save();

                continue;
            }

            TerrainType::create([
                'code' => $type['code'],
                'pin_color' => $type['pin_color'],
                'type' => $translations,
            ]);
        }
    }
}
