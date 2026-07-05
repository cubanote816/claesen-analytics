<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;

// Runs every FieldOps catalog seeder ported from the deprecated satellite
// (api-claesen-sport-app) in dependency order — LuminaireSubgroupSeeder must
// run before LuminaireTypeSeeder, which looks subgroups up by brand.
// All individual seeders are idempotent (firstOrCreate), safe to re-run.
class FieldOpsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessTypeSeeder::class,
            ElectricalBoardTypeSeeder::class,
            SafetyTypeSeeder::class,
            StructureTypeSeeder::class,
            TerrainTypeSeeder::class,
            LuminaireFrameTypeSeeder::class,
            LuminaireSubgroupSeeder::class,
            LuminaireTypeSeeder::class,
        ]);
    }
}
