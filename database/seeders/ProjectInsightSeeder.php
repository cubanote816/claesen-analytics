<?php

namespace Database\Seeders;

use App\Models\ProjectInsight;
use Illuminate\Database\Seeder;

class ProjectInsightSeeder extends Seeder
{
    public function run(): void
    {
        ProjectInsight::create([
            'project_id' => '2024-001-MOCK',
            'insight_type' => 'audit_budget',
            'efficiency_score' => 85.50,
            'critical_leak' => 'Labor Inefficiency',
            'ai_summary' => 'Dit project presteert goed, maar er is een lichte afwijking in de arbeidskosten in vergelijking met het budget.',
            'golden_rule' => 'Controleer de urenregistratie van het montageteam.',
            'last_data_hash' => md5('mock_data'),
            'last_audited_at' => now(),
        ]);

        ProjectInsight::create([
            'project_id' => '2024-002-RISK',
            'insight_type' => 'post-mortem',
            'efficiency_score' => 45.20,
            'critical_leak' => 'Unbilled Materials',
            'ai_summary' => 'Kritieke waarschuwing: Er zijn materialen ter waarde van €5.000 niet gefactureerd.',
            'golden_rule' => 'Factureer onmiddellijk de openstaande materiaalposten.',
            'last_data_hash' => md5('mock_data_2'),
            'last_audited_at' => now()->subDays(2),
        ]);
    }
}
