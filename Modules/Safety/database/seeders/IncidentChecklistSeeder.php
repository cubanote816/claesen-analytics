<?php

declare(strict_types=1);

namespace Modules\Safety\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Safety\Models\Checklist;

class IncidentChecklistSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Asegurar la existencia del Checklist para incidentes
            $checklist = Checklist::firstOrCreate(
                ['type' => 'incident', 'name' => 'Ongevallen en Incidenten Melding'],
                ['is_active' => true]
            );

            // 2. Limpiar preguntas anteriores para mantener idempotencia
            $checklist->questions()->delete();

            // 3. Definición de preguntas lógicas orientadas a VCA y sector eléctrico
            $questions = [
                // Acciones inmediatas
                'Is er direct eerste hulp (EHBO) of medische bijstand verleend aan het slachtoffer?',
                'Is de werkplek (inclusief elektrische installaties) direct veiliggesteld om verdere ongevallen te voorkomen?',
                
                // Entorno y Procedimientos (Específico Claesen)
                'Werd er gewerkt aan of nabij onder spanning staande elektrische installaties?',
                'Werd er gewerkt op hoogte (bijv. steigers, hoogwerkers of ladders)?',
                'Is er voorafgaand aan de werkzaamheden een LMRA (Laatste Minuut Risico Analyse) uitgevoerd?',
                
                // Equipamiento y PBM
                'Werden de vereiste Persoonlijke Beschermingsmiddelen (PBM) voor deze specifieke taak correct gedragen?',
                'Waren de gebruikte gereedschappen, meetapparatuur en materialen gekeurd en vrij van defecten?',
                
                // Consecuencias y Registro
                'Zijn er getuigen van het incident aanwezig en zijn hun contactgegevens genoteerd?',
                'Is er sprake van materiële schade of milieuschade (bijv. lekkage van gevaarlijke stoffen)?',
                'Is de projectleider en/of directie onmiddellijk op de hoogte gesteld van het incident?'
            ];

            // 4. Inserción masiva con ordenamiento
            $insertData = [];
            foreach ($questions as $index => $text) {
                $insertData[] = [
                    'checklist_id' => $checklist->id,
                    'text_nl'      => $text,
                    'order'        => ($index + 1) * 10, // Espacios de 10 para futuras inserciones intermedias
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            }

            $checklist->questions()->insert($insertData);
        });
    }
}
