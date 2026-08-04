<?php

declare(strict_types=1);

namespace Modules\FieldOps\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\Core\Models\User;
use Modules\FieldOps\Models\Complex;
use Modules\FieldOps\Models\FoClient;

/**
 * Two FoClient tenants with their own Complexes, purely for exercising tenant
 * isolation (CLA-266) in local QA — one is the client already linked to
 * qa.cliente@claesen-verlichting.test (adds a few Complexes to it), the other
 * is a brand new fake client + user (qa.cliente2@...) that should never see the
 * first tenant's data.
 *
 * Both FoClient/Complex rows created here are fake (relation_id=null) — this does
 * NOT reintroduce manual Client/Complex creation disabled by FO-012/FO-013/CLA-266,
 * which governs the app's write paths (API/Filament), not local QA seeding. The
 * null relation_id is the same signal the rest of the module already uses to tell
 * a CAFCA-synced row apart from one that isn't.
 */
class QaTenantIsolationSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            Log::info('QaTenantIsolationSeeder: refusing to run outside local/testing.');

            return;
        }

        $this->seedComplexesForExistingQaClient();
        $this->seedSecondTenant();
    }

    private function seedComplexesForExistingQaClient(): void
    {
        $qaCliente = User::where('email', 'qa.cliente@claesen-verlichting.test')->first();
        $client = $qaCliente?->fieldOpsClients()->first();

        if (! $client) {
            Log::info('QaTenantIsolationSeeder: qa.cliente@claesen-verlichting.test has no linked FoClient yet — skipping tenant A complexes.');

            return;
        }

        $this->createComplexes($client, [
            ['name' => 'QA Tenant A - Sportpark Noord', 'lat' => 51.1800, 'lng' => 5.0700],
            ['name' => 'QA Tenant A - Sportpark Zuid', 'lat' => 51.1650, 'lng' => 5.0850],
            ['name' => 'QA Tenant A - Trainingscomplex', 'lat' => 51.1720, 'lng' => 5.0600],
        ]);
    }

    private function seedSecondTenant(): void
    {
        $client = FoClient::firstOrCreate(
            ['name' => 'QA Tenant B Sportclub'],
            [
                'city' => 'Antwerpen',
                'street' => 'Testlaan 2',
                'phone' => '+32 3 000 00 00',
                'email' => 'qa.tenant.b@claesen-verlichting.test',
                'language' => 'nl',
            ]
        );

        $user = User::updateOrCreate(
            ['email' => 'qa.cliente2@claesen-verlichting.test'],
            [
                'name' => 'QA Cliente 2',
                'password' => Hash::make('QaCliente2123!'),
                'password_set_at' => now(),
                'is_active' => true,
            ]
        );
        $user->syncRoles(['client']);
        $user->fieldOpsClients()->syncWithoutDetaching([
            $client->id => ['is_active' => true, 'can_view' => true, 'can_report' => true, 'can_manage_contacts' => false],
        ]);

        $this->createComplexes($client, [
            ['name' => 'QA Tenant B - Atletiekpark', 'lat' => 51.2200, 'lng' => 4.4100],
            ['name' => 'QA Tenant B - Hockeyclub', 'lat' => 51.2350, 'lng' => 4.4250],
            ['name' => 'QA Tenant B - Tennispark', 'lat' => 51.2100, 'lng' => 4.3950],
        ]);
    }

    /**
     * @param array<int, array{name: string, lat: float, lng: float}> $complexes
     */
    private function createComplexes(FoClient $client, array $complexes): void
    {
        foreach ($complexes as $data) {
            Complex::firstOrCreate(
                ['client_id' => $client->id, 'name' => $data['name']],
                [
                    'street' => 'Testlaan 1',
                    'city' => $client->city ?? 'Antwerpen',
                    'zipcode' => '2000',
                    'lat' => $data['lat'],
                    'lng' => $data['lng'],
                    'zoom' => 17.00,
                ]
            );
        }
    }
}
