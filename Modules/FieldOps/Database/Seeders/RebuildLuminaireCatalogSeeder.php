<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

/**
 * Destructively rebuilds only the luminaire catalog and its dependent records.
 * Luminaire frames and maintenance belonging to other equipment are preserved.
 */
class RebuildLuminaireCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            FoMaintenanceRecord::withTrashed()
                ->where('maintainable_type', Luminaire::class)
                ->forceDelete();

            Luminaire::withTrashed()->forceDelete();
            LuminaireType::query()->delete();
            LuminaireSubgroup::query()->delete();

            $this->call([
                LuminaireSubgroupSeeder::class,
                LuminaireTypeSeeder::class,
            ]);
        });
    }
}
