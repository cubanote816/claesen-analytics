<?php

namespace App\Services\Cafca;

use App\Models\Employee;
use App\Models\Cafca\Employee as legacyEmployee;
use Illuminate\Support\Facades\Log;

class EmployeeSyncService
{
    /**
     * Synchronize employees from Legacy SQL Server to Local MySQL.
     * 
     * @return array statistics of the sync operation
     */
    public function sync(): array
    {
        $stats = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        try {
            // Get the last modification timestamp we have in our local DB
            $lastSync = Employee::max('legacy_ts_modif');

            // Query legacy employees modified since last sync
            $query = legacyEmployee::query();

            if ($lastSync) {
                $query->where('ts_modif', '>', $lastSync);
            }

            $legacyEmployees = $query->get();

            foreach ($legacyEmployees as $legacy) {
                try {
                    // Check if it exists locally to distinguish created vs updated for stats
                    $exists = Employee::where('id', trim($legacy->id))->exists();

                    Employee::updateOrCreate(
                        ['id' => trim($legacy->id)],
                        [
                            'name' => trim($legacy->name),
                            'function' => trim($legacy->functie ?? __('employees/resource.fields.function_default')),
                            'mobile' => trim($legacy->mobile ?: $legacy->tel),
                            'email' => trim($legacy->email),
                            'street' => trim($legacy->street),
                            'zip' => trim($legacy->zipcode),
                            'city' => trim($legacy->city),
                            'country' => trim($legacy->country),
                            'fl_active' => $legacy->fl_active,
                            'birth_date' => $legacy->birthday,
                            'employment_date' => $legacy->in_dienst,
                            'termination_date' => $legacy->uit_dienst,
                            'legacy_ts_modif' => $legacy->ts_modif,
                        ]
                    );

                    if ($exists) {
                        $stats['updated']++;
                    } else {
                        $stats['created']++;
                    }
                } catch (\Exception $e) {
                    Log::error("Error syncing employee ID {$legacy->id}: " . $e->getMessage());
                    $stats['errors']++;
                }
            }

            // If no new/modified records found, we might want to check the count
            if ($legacyEmployees->isEmpty()) {
                $stats['skipped'] = legacyEmployee::count();
            }
        } catch (\Exception $e) {
            Log::error("Critical error in EmployeeSyncService: " . $e->getMessage());
            throw $e;
        }

        return $stats;
    }
}
