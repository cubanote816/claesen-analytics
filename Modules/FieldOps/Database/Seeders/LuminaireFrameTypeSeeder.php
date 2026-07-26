<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\LuminaireFrameType;

// Unlike the old satellite (api-claesen-sport-app), LuminaireFrameType here is a
// plain string (no HasTranslations) — no locale variants to port. The name of
// each type is the name of its reference image (CLA-278), not an arbitrary label.
class LuminaireFrameTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Superseded by the real headframe catalog below. Soft-deleted (not
        // force-deleted): existing fo_luminaire_frames rows can already reference
        // these ids (FK constraint), and the admin table hides trashed rows by
        // default while still allowing a manual Restore (LuminaireFrameTypeResource).
        LuminaireFrameType::whereIn('name', ['Traverse 1', 'Traverse 2', 'Traverse 3', 'Traverse 4', 'Traverse 5', 'Balcony'])
            ->get()
            ->each(fn (LuminaireFrameType $type) => $type->delete());

        $types = [
            ['name' => 'Curved stadium headframe', 'image' => '/assets/frame-types/curved-stadium-headframe.png'],
            ['name' => 'Fixed cross-arm headframe', 'image' => '/assets/frame-types/fixed-cross-arm-headframe.png'],
            ['name' => 'Fixed platform stadium headframe', 'image' => '/assets/frame-types/fixed-platform-stadium-headframe.png'],
            ['name' => 'Lowering headframe', 'image' => '/assets/frame-types/lowering-headframe.png'],
            ['name' => 'Oval stadium headframe', 'image' => '/assets/frame-types/oval-stadium-headframe.png'],
            ['name' => 'Tubular cage headframe', 'image' => '/assets/frame-types/tubular-cage-headframe.png'],
        ];

        foreach ($types as $type) {
            LuminaireFrameType::updateOrCreate(['name' => $type['name']], ['image' => $type['image']]);
        }
    }
}
