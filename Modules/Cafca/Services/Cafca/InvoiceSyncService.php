<?php
 
namespace Modules\Cafca\Services\Cafca;
 
use Modules\Cafca\Models\Invoice as LegacyInvoice;
use Modules\Performance\Models\Mirror\MirrorInvoice;
use Illuminate\Support\Facades\Log;
 
class InvoiceSyncService
{
    /**
     * Synchronize invoices from Legacy SQL Server to Local MySQL Mirror.
     * 
     * @return array statistics of the sync operation
     */
    public function sync(): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];
 
        try {
            // Invoices are many, we might want to sync only recent ones or a big batch
            // For now, we fetch invoices from projects that were recently modified or all
            // But let's stick to a full sync of important fields if not too large
            $legacyInvoices = LegacyInvoice::query()
                ->select(['id', 'project_id', 'total_price_vat_excl', 'date'])
                ->whereNotNull('project_id')
                ->get();
 
            foreach ($legacyInvoices as $legacy) {
                try {
                    $exists = MirrorInvoice::where('id', trim($legacy->id))->exists();
 
                    MirrorInvoice::updateOrCreate(
                        ['id' => trim($legacy->id)],
                        [
                            'project_id' => trim($legacy->project_id),
                            'total_price_vat_excl' => $legacy->total_price_vat_excl ?? 0,
                            'date' => $legacy->date,
                        ]
                    );
 
                    if ($exists) {
                        $stats['updated']++;
                    } else {
                        $stats['created']++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error syncing invoice ID {$legacy->id}: " . $e->getMessage());
                    $stats['errors']++;
                }
            }
 
        } catch (\Exception $e) {
            Log::error("Critical error in InvoiceSyncService: " . $e->getMessage());
            throw $e;
        }
 
        return $stats;
    }
}
