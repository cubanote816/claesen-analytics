<?php
 
namespace Modules\Performance\Console\Commands;
 
use Illuminate\Console\Command;
use Modules\Cafca\Services\Cafca\PerformanceSyncMasterService;
 
class SyncAllCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:sync-all';
 
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronización secuencial de empleados, facturas y auditoría de proyectos desde el legacy.';
 
    /**
     * Execute the console command.
     */
    public function handle(PerformanceSyncMasterService $syncService)
    {
        $this->info('🚀 Iniciando sincronización de Performance...');
 
        $results = $syncService->runAll();
 
        // Display stats
        $this->info("✅ Empleados: {$results['employees']['created']} creados, {$results['employees']['updated']} actualizados.");
        $this->info("✅ Facturas: {$results['invoices']['created']} creadas, {$results['invoices']['updated']} actualizadas.");
        
        if ($results['audit']) {
            $this->info("📡 Lote de Auditoría AI despachado (ID: {$results['audit']['batch_id']}, Jobs: {$results['audit']['total_jobs']}).");
        } else {
            $this->warn("⚠️ No se encontraron proyectos para auditar.");
        }
 
        $this->info('✨ Sincronización completada con éxito.');
    }
}
