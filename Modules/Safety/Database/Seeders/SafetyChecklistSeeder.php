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
                'Zijn de werkplek en de toegangswegen ordelijk en veilig toegankelijk?',
                'Worden de vereiste Persoonlijke Beschermingsmiddelen (PBM) correct gedragen?',
                'Zijn alle elektrische gereedschappen gekeurd en zonder zichtbare gebreken?',
                'Is de valbeveiliging (steigers/harnassen) in orde en correct toegepast?',
                'Zijn de brandblusmiddelen aanwezig, gekeurd en direct bereikbaar?',
                'Is de werkplek voldoende verlicht voor een veilige uitvoering van de taken?', // Claesen context
                'Zijn er EHBO-voorzieningen aanwezig en is de locatie bekend bij de medewerkers?',
                'Worden gevaarlijke stoffen veilig opgeslagen en gebruikt conform de voorschriften?'
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
