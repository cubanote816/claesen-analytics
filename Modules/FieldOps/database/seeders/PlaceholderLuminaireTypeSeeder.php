<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

/**
 * CLA-391 (CLA-390 Fase 2) — a single, deliberately generic catalog entry
 * used for luminaires detected by the AI in a frame photo whose type could
 * not be identified. It is never presented to the technician as a real
 * product choice in the normal "add luminaire" flow (it lives outside the
 * real brand subgroups) — it only gets assigned automatically when
 * confirming an unidentified detection, so the installation still records a
 * real position instead of being skipped, until it's swapped for the real
 * type via the existing "Replace luminaire" flow (CLA-265).
 */
class PlaceholderLuminaireTypeSeeder extends Seeder
{
    public const SUBGROUP_BRAND = 'Unknown';

    public const TYPE_NAME = 'Unidentified fixture — pending replacement';

    public function run(): void
    {
        $subgroup = LuminaireSubgroup::updateOrCreate(
            ['group_name' => 'Other', 'brand' => self::SUBGROUP_BRAND],
            ['group_name' => 'Other', 'brand' => self::SUBGROUP_BRAND],
        );

        LuminaireType::updateOrCreate(
            [
                'luminaire_subgroup_id' => $subgroup->id,
                'name' => self::TYPE_NAME,
            ],
            [
                'product_family' => null,
                'model_reference' => null,
                'typical_application' => null,
                'image' => null,
                'image_source_url' => null,
            ],
        );
    }

    /**
     * @return array{luminaire_type_id: int, luminaire_subgroup_id: int}|null
     *               Null when the one-time seed hasn't run yet in this environment.
     */
    public static function resolveIds(): ?array
    {
        $type = LuminaireType::whereHas('subgroup', function ($query): void {
            $query->where('group_name', 'Other')->where('brand', self::SUBGROUP_BRAND);
        })
            ->where('name', self::TYPE_NAME)
            ->first();

        return $type ? [
            'luminaire_type_id' => $type->id,
            'luminaire_subgroup_id' => $type->luminaire_subgroup_id,
        ] : null;
    }
}
