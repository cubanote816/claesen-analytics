<?php

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\FieldOps\Models\LuminaireSubgroup;
use Modules\FieldOps\Models\LuminaireType;

class LuminaireTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'group_name' => 'LED',
                'brand' => 'Philips / Signify',
                'product_family' => 'OptiVision LED gen3.5',
                'model_reference' => 'BVP518',
                'typical_application' => 'Recreational football, tennis, padel, hockey and medium-size outdoor areas',
                'image' => '/assets/luminaire-types/bvp518-optivision-led-gen3-5.png',
                'image_source_url' => 'https://www.signify.com/en-gb/prof/outdoor-luminaires/sports-and-area-floodlighting/signify-optivision-led-gen35/LP_CF_BVP518_EU/family',
            ],
            [
                'group_name' => 'LED',
                'brand' => 'Philips / Signify',
                'product_family' => 'OptiVision LED gen3.5',
                'model_reference' => 'BVP528',
                'typical_application' => 'Large sports fields, high-mast installations and high-output retrofits',
                'image' => '/assets/luminaire-types/bvp528-optivision-led-gen3-5.png',
                'image_source_url' => 'https://www.signify.com/en-gb/prof/outdoor-luminaires/sports-and-area-floodlighting/signify-optivision-led-gen35/LP_CF_BVP528_EU/family',
            ],
            [
                'group_name' => 'LED',
                'brand' => 'Philips / Signify',
                'product_family' => 'ArenaVision LED gen3.5',
                'model_reference' => 'BVP418',
                'typical_application' => 'Indoor arenas and outdoor competition venues',
                'image' => '/assets/luminaire-types/bvp418-arenavision-led-gen3-5.png',
                'image_source_url' => 'https://www.lighting.philips.com/prof/outdoor-luminaires/sports-and-area-floodlighting/arenavision-led-gen3-5/LP_CF_BVP418_EU/family',
            ],
            [
                'group_name' => 'LED',
                'brand' => 'Philips / Signify',
                'product_family' => 'ArenaVision LED gen3.5 C',
                'model_reference' => 'BVP428',
                'typical_application' => 'Stadiums, broadcast-ready venues and dynamic-lighting projects',
                'image' => '/assets/luminaire-types/bvp428-arenavision-led-gen3-5-c.png',
                'image_source_url' => 'https://www.lighting.philips.com/content/signify-multibrand/aa/en/family/product-family-page.LP_CF_BVP428_EU.html',
            ],
            [
                'group_name' => 'LED',
                'brand' => 'Schréder',
                'product_family' => 'OMNISTAR',
                'model_reference' => 'OMNISTAR LED',
                'typical_application' => 'Sports grounds, stadiums, industrial yards and large outdoor areas',
                'image' => '/assets/luminaire-types/omnistar-led.png',
                'image_source_url' => 'https://www.schreder.com/en/products/omnistar-industrial-tunnel-sport-large-area-lighting',
            ],
            [
                'group_name' => 'LED',
                'brand' => 'Thorn',
                'product_family' => 'Altis Sport',
                'model_reference' => 'Altis Sport LED',
                'typical_application' => 'Sports facilities requiring glare control, optics flexibility and broadcast performance',
                'image' => '/assets/luminaire-types/altis-sport-led.jpg',
                'image_source_url' => 'https://professional-electrician.com/products/thorn-lighting-introduce-altis-powerful-led-floodlight/',
            ],
            [
                'group_name' => 'LED',
                'brand' => 'Musco',
                'product_family' => 'Total Light Control',
                'model_reference' => 'TLC for LED®',
                'typical_application' => 'Turnkey sports-lighting projects with strict spill-light and glare requirements',
                'image' => '/assets/luminaire-types/tlc-for-led.jpg',
                'image_source_url' => 'https://www.musco.com/we/tlcled/',
            ],
            [
                'group_name' => 'HID — legacy only',
                'brand' => 'Philips',
                'product_family' => 'OptiVision HID',
                'model_reference' => 'MVP507 OptiVision HID',
                'typical_application' => 'Maintenance and replacement of existing installations',
                'image' => '/assets/luminaire-types/mvp507-optivision-hid.png',
                'image_source_url' => 'https://www.assets.lighting.philips.com/is/content/PhilipsLighting/comf2215-pss-en_bg',
            ],
            [
                'group_name' => 'HID — legacy only',
                'brand' => 'Philips',
                'product_family' => 'PowerVision HID',
                'model_reference' => 'MVF024 PowerVision HID',
                'typical_application' => 'Maintenance and replacement of existing installations',
                'image' => '/assets/luminaire-types/mvf024-powervision-hid.png',
                'image_source_url' => 'https://www.assets.lighting.philips.com/is/content/PhilipsLighting/comf2201-pss-en_sg',
            ],
            [
                'group_name' => 'HID — legacy only',
                'brand' => 'Philips',
                'product_family' => 'ArenaVision HID',
                'model_reference' => 'MVF403',
                'typical_application' => 'Maintenance of legacy stadium installations',
                'image' => '/assets/luminaire-types/mvf403-arenavision-hid.png',
                'image_source_url' => 'https://www.akselku.com/product-page/arenavision-mvf403-c',
            ],
        ];

        foreach ($types as $type) {
            $subgroup = LuminaireSubgroup::query()
                ->where('group_name', $type['group_name'])
                ->where('brand', $type['brand'])
                ->first();

            if (! $subgroup) {
                continue;
            }

            LuminaireType::updateOrCreate(
                [
                    'luminaire_subgroup_id' => $subgroup->id,
                    'model_reference' => $type['model_reference'],
                ],
                [
                    'name' => $type['model_reference'],
                    'product_family' => $type['product_family'],
                    'typical_application' => $type['typical_application'],
                    'image' => $type['image'],
                    'image_source_url' => $type['image_source_url'],
                ],
            );
        }
    }
}
