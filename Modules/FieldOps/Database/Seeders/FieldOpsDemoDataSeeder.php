<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\ElectricalBoard;
use Modules\FieldOps\Models\FoClient;
use Modules\FieldOps\Models\FoMaintenanceRecord;
use Modules\FieldOps\Models\FoMaintenanceType;
use Modules\FieldOps\Models\Luminaire;
use Modules\FieldOps\Models\LuminaireFrame;
use Modules\FieldOps\Models\Structure;
use Modules\FieldOps\Models\Terrain;

/**
 * Jerarquía demo (terreno → estructura → armazón → luminarias → mantenimiento) para
 * poder ejercitar en dev las vistas del módulo FieldOps que hoy no tienen ningún dato
 * real — a diferencia de FoClient/Complex, estas entidades nunca vienen del sync CAFCA,
 * así que no hay forma de "recuperarlas" desde el ERP tras un reset de la BD.
 *
 * Se cuelga de un Complex REAL ya sincronizado (nunca crea Client/Complex a mano — eso
 * violaría la creación manual deshabilitada de FO-012/FO-013). Si ese Complex no existe
 * todavía (entorno sin sync CAFCA corrido), no hace nada en vez de fallar.
 */
class FieldOpsDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $client = FoClient::where('name', 'like', '%Balen%Verbroeder%')->first();
        $complex = $client
            ? Complex::where('client_id', $client->id)->where('name', 'Stadion Bleukens')->first()
            : null;

        if (! $complex) {
            Log::info('FieldOpsDemoDataSeeder: complex "Stadion Bleukens" not found (CAFCA sync not run yet?) — skipping demo data.');

            return;
        }

        $hoofdveld = Terrain::firstOrCreate(
            ['complex_id' => $complex->id, 'name->nl' => 'Hoofdveld'],
            [
                'terrain_type_id' => 1, // Soccer
                'name' => ['nl' => 'Hoofdveld', 'fr' => 'Terrain principal', 'en' => 'Main field', 'de' => 'Hauptfeld'],
            ]
        );

        $trainingsveldB = Terrain::firstOrCreate(
            ['complex_id' => $complex->id, 'name->nl' => 'Trainingsveld B'],
            [
                'terrain_type_id' => 1, // Soccer
                'name' => ['nl' => 'Trainingsveld B', 'fr' => "Terrain d'entraînement B", 'en' => 'Training field B', 'de' => 'Trainingsfeld B'],
            ]
        );

        $lichtmastNoord = Structure::firstOrCreate(
            ['structure_type_id' => 1, 'height' => 900, 'access_type_id' => 3, 'safety_type_id' => 2],
            [
                'access_active' => true,
                'safety_certified' => true,
                'info' => [
                    'nl' => 'Hoeklichtmast, gedeeld tussen Hoofdveld en Trainingsveld B.',
                    'fr' => "Mât d'éclairage d'angle, partagé entre le terrain principal et le terrain B.",
                    'en' => 'Corner floodlight pole, shared between the main field and training field B.',
                    'de' => 'Eck-Flutlichtmast, gemeinsam genutzt von Hauptfeld und Trainingsfeld B.',
                ],
            ]
        );
        $lichtmastNoord->terrains()->syncWithoutDetaching([$hoofdveld->id, $trainingsveldB->id]);

        $lichtmastZuid = Structure::firstOrCreate(
            ['structure_type_id' => 1, 'height' => 900, 'access_type_id' => 3, 'safety_type_id' => 2, 'cafca_material_id' => 9901],
            [
                'access_active' => true,
                'safety_certified' => true,
                'info' => [
                    'nl' => 'Lichtmast aan de zuidzijde van het hoofdveld.',
                    'fr' => "Mât d'éclairage au sud du terrain principal.",
                    'en' => 'Floodlight pole on the south side of the main field.',
                    'de' => 'Flutlichtmast an der Südseite des Hauptfelds.',
                ],
            ]
        );
        $lichtmastZuid->terrains()->syncWithoutDetaching([$hoofdveld->id]);

        $frame = LuminaireFrame::firstOrCreate(
            ['luminaire_frame_type_id' => 3],
            []
        );
        $frame->structures()->syncWithoutDetaching([$lichtmastNoord->id]);

        $board = ElectricalBoard::firstOrCreate(
            ['electrical_board_type_id' => 1, 'location_description->nl' => 'Kast A - Hoofdveld'],
            [
                'location_description' => [
                    'nl' => 'Kast A - Hoofdveld',
                    'fr' => 'Armoire A - Terrain principal',
                    'en' => 'Cabinet A - Main field',
                    'de' => 'Schrank A - Hauptfeld',
                ],
            ]
        );
        $board->complexes()->syncWithoutDetaching([$complex->id]);
        $board->terrains()->syncWithoutDetaching([$hoofdveld->id]);
        $board->structures()->syncWithoutDetaching([$lichtmastNoord->id, $lichtmastZuid->id]);

        $positions = [
            ['x' => 18.0, 'y' => 22.0, 'sx' => 1.00, 'sy' => 1.00],
            ['x' => 50.0, 'y' => 20.0, 'sx' => 0.90, 'sy' => 0.90],
            ['x' => 82.0, 'y' => 24.0, 'sx' => 1.25, 'sy' => 1.25],
            ['x' => 20.0, 'y' => 70.0, 'sx' => 0.85, 'sy' => 0.85],
            ['x' => 50.0, 'y' => 74.0, 'sx' => 1.00, 'sy' => 1.00],
            ['x' => 80.0, 'y' => 72.0, 'sx' => 1.10, 'sy' => 1.10],
        ];

        $luminaires = [];
        foreach ($positions as $i => $pos) {
            $n = $i + 1;
            $luminaires[$n] = Luminaire::firstOrCreate(
                ['serial_number' => sprintf('DEMO-LN-%03d', 40 + $n)],
                [
                    'luminaire_frame_id' => $frame->id,
                    'luminaire_type_id' => 1, // BVP525 OptiVision LED Gen2
                    'luminaire_subgroup_id' => 1, // LED / Philips Optivision LED
                    'frame_position' => $n,
                    'frame_x' => $pos['x'],
                    'frame_y' => $pos['y'],
                    'scale_x' => $pos['sx'],
                    'scale_y' => $pos['sy'],
                ]
            );
        }

        $preventiveType = FoMaintenanceType::where('code', FoMaintenanceType::CODE_PREVENTIVE)->first();
        $correctiveType = FoMaintenanceType::where('code', FoMaintenanceType::CODE_CORRECTIVE)->first();
        $emergencyType = FoMaintenanceType::where('code', FoMaintenanceType::CODE_EMERGENCY)->first();

        FoMaintenanceRecord::firstOrCreate(
            ['maintainable_type' => Luminaire::class, 'maintainable_id' => $luminaires[3]->id, 'fo_maintenance_type_id' => $preventiveType->id],
            [
                'employee_id' => '100',
                'maintenance_at' => now()->subDays(12),
                'notes' => 'Jaarlijkse controle: optiek gereinigd, bevestiging gecontroleerd.',
                'details' => ['task' => 'optical_cleaning', 'result' => 'ok'],
                'downtime_hours' => 0.5,
            ]
        );

        FoMaintenanceRecord::firstOrCreate(
            ['maintainable_type' => ElectricalBoard::class, 'maintainable_id' => $board->id, 'fo_maintenance_type_id' => $correctiveType->id],
            [
                'employee_id' => '101',
                'maintenance_at' => now()->subDays(20),
                'problem_description' => 'Losse aansluiting in verdeelkast.',
                'root_cause' => 'Trilling door onderhoud grasmaaier vlakbij.',
                'solution_applied' => 'Aansluiting vastgezet en herbevestigd.',
                'problem_reported_at' => now()->subDays(20)->subHours(3),
                'problem_solved_at' => now()->subDays(20),
                'downtime_hours' => 3.0,
            ]
        );

        FoMaintenanceRecord::firstOrCreate(
            ['maintainable_type' => ElectricalBoard::class, 'maintainable_id' => $board->id, 'is_emergency' => true],
            [
                'fo_maintenance_type_id' => $emergencyType->id,
                'client_id' => $client->id,
                'reported_by_client' => true,
                'priority' => 'high',
                'contact_person' => 'J. Van Damme',
                'contact_phone' => '014 12 34 56',
                'location_details' => 'Geen stroom op Hoofdveld tijdens training.',
                'maintenance_at' => now()->subHours(6),
                'problem_reported_at' => now()->subHours(6),
                'problem_solved_at' => null,
            ]
        );
    }
}
