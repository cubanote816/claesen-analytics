<?php

declare(strict_types=1);

namespace Modules\Intelligence\Services;

use Illuminate\Support\Facades\Artisan;

/**
 * CLA-439 — orquesta los 3 sync manuales que un admin puede disparar desde
 * "Mirror Sync Status" (empleados/clientes/complejos), distinto del botón
 * "Refresh now" ya existente (que corre el mirror CAFCA completo, incluyendo
 * proyectos/labor/materiales/facturas — mucho más pesado y no relacionado).
 *
 * "Clients"/"complexes" son en realidad 2 pasos cada uno: refrescar el mirror
 * crudo específico (--relations/--deliveries, sin tocar el resto del mirror)
 * y luego el bridge FieldOps (FO-012/FO-013) que lo convierte en fo_clients/
 * fo_complexes reales — mismo orden y razón que el schedule nocturno
 * (Modules\Intelligence\Providers\IntelligenceServiceProvider).
 */
class ManualDataSyncService
{
    public function syncEmployees(): void
    {
        Artisan::call('app:sync-employees');
    }

    public function syncClients(): void
    {
        Artisan::call('intelligence:sync-mirror', ['--relations' => true]);
        Artisan::call('fieldops:sync-clients-from-relations');
    }

    public function syncComplexes(): void
    {
        Artisan::call('intelligence:sync-mirror', ['--deliveries' => true]);
        Artisan::call('fieldops:sync-complexes-from-relation-deliveries');
    }

    public function syncAll(): void
    {
        $this->syncEmployees();
        $this->syncClients();
        $this->syncComplexes();
    }
}
