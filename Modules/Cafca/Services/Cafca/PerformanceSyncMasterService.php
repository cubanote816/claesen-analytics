<?php
 
namespace Modules\Cafca\Services\Cafca;
 
use Modules\Cafca\Services\CafcaSyncService;
use Illuminate\Support\Facades\Log;
 
class PerformanceSyncMasterService
{
    protected $employeeSync;
    protected $invoiceSync;
    protected $cafcaSync;
 
    public function __construct(
        EmployeeSyncService $employeeSync,
        InvoiceSyncService $invoiceSync,
        CafcaSyncService $cafcaSync
    ) {
        $this->employeeSync = $employeeSync;
        $this->invoiceSync = $invoiceSync;
        $this->cafcaSync = $cafcaSync;
    }
 
    /**
     * Run the complete synchronization pipeline.
     * 
     * @return array
     */
    public function runAll(): array
    {
        Log::info("Starting Performance Sync Master...");
 
        $results = [
            'employees' => $this->employeeSync->sync(),
            'invoices' => $this->invoiceSync->sync(),
            'audit' => null,
        ];
 
        // Trigger Project AI Audits (Active projects only for safety/performance)
        // Note: The audit process is dispatched as a Batch job
        $batch = $this->cafcaSync->auditProjects();
        
        if ($batch) {
            $results['audit'] = [
                'batch_id' => $batch->id,
                'total_jobs' => $batch->totalJobs,
                'status' => 'dispatched',
            ];
        }
 
        Log::info("Performance Sync Master finished.");
 
        return $results;
    }
}
