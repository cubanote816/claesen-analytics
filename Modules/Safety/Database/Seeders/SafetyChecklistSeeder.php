<?php

declare(strict_types=1);

namespace Modules\Safety\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Safety\Models\Checklist;

class SafetyChecklistSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $checklist = Checklist::firstOrCreate(
                ['name' => 'Maandelijkse VCA Werkplekinspectie'],
                ['is_active' => true]
            );

            // Borramos las preguntas previas si el seeder se corre múltiples veces
            $checklist->questions()->delete();

            $questions = [
                // Regels & Instructies
                'Worden de veiligheidsregels uit het instructieboekje strikt nageleefd?',
                'Is het V&G-plan op de werkplek aanwezig en door iedereen getekend?',
                'Is het startgesprekformulier door alle aanwezigen ingevuld en getekend?',
                
                // Arbeidsmiddelen
                'Zijn alle steigers veilig gemonteerd en voorzien van een geldige steigerkaart?',
                'Zijn alle elektrische gereedschappen voorzien van een geldige keuringssticker?',
                'Zijn ladders, trappen en andere klimmaterialen in goede staat en gekeurd?',
                
                // PBM's
                'Worden de vereiste Persoonlijke Beschermingsmiddelen (PBM) correct gedragen?',
                'Zijn er voldoende reserve-PBM\'s aanwezig op de projectlocatie?',
                
                // Opslag & Gevaarlijke stoffen
                'Worden gevaarlijke stoffen veilig opgeslagen conform de voorschriften?',
                'Zijn de Veiligheidsinformatiebladen (VIB) van gebruikte stoffen direct raadpleegbaar?',
                
                // Noodvoorzieningen & Orde
                'Is de EHBO-trommel compleet, houdbaar en direct bereikbaar?',
                'Zijn de brandblusmiddelen aanwezig, gekeurd en vrij van obstakels?',
                'Is de werkplek ordelijk en zijn de toegangswegen veilig toegankelijk?',
                
                // Algemeen
                'Wordt de Laatste Minuut Risico Analyse (LMRA) dagelijks consequent uitgevoerd?',
                'Is de werkplek voldoende verlicht voor een veilige uitvoering van de taken?',
            ];

            foreach ($questions as $index => $text) {
                $checklist->questions()->create([
                    'text_nl' => $text,
                    'order'   => ($index + 1) * 10, // Saltos de 10 para permitir reordenamiento futuro
                ]);
            }
        });
    }
}
