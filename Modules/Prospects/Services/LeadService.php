<?php

namespace Modules\Prospects\Services;

use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\ProspectLocation;
use Modules\Prospects\Models\Region;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadService
{
    /**
     * Persists a contact lead from the public website into the Prospects domain.
     *
     * @param array $data Expected keys: name, email, message, source, ip
     * @return Prospect
     */
    public function persistContactLead(array $data): Prospect
    {
        return DB::transaction(function () use ($data) {
            // Normalize email to avoid fragile deduplication
            $email = strtolower(trim($data['email']));

            // Check or create prospect safely using a lock or firstOrCreate pattern
            // To properly avoid race conditions, firstOrCreate is executed on the location.
            // Wait, we need to ensure the prospect exists first if creating.
            
            $existingLocation = ProspectLocation::where('email', $email)->lockForUpdate()->first();

            if ($existingLocation) {
                $prospect = $existingLocation->prospect;
                Log::info('Lead/Contact received for existing prospect.', ['prospect_id' => $prospect->id, 'email' => $email]);
                return $prospect;
            }

            $prospect = Prospect::create([
                'name' => $data['name'],
                'type' => 'lead',
                'channel' => 'website_contact',
                // F4/CLA-478 bug fix: prospects_prospects.region_id is
                // NOT NULL (2026_04_03_204632_make_region_id_required_on_
                // prospects_prospects_table.php, a deliberate decision for
                // the federation-club domain this table primarily serves)
                // — a website contact-form lead genuinely has no known
                // region at creation time. Never reached before this
                // ticket's own end-to-end test: no prior test exercised
                // this method through a real, unmocked HTTP request.
                // 'Brussel' is the same fallback that migration's own
                // backfill already used (id 11 there; resolved by slug
                // here, not a hardcoded id, since it's guaranteed seeded
                // by 2026_04_03_190000_create_prospects_regions_table.php).
                'region_id' => Region::where('slug', 'brussel')->value('id'),
            ]);

            try {
                ProspectLocation::create([
                    'prospect_id' => $prospect->id,
                    'contact_type' => 'primary',
                    'contact_name' => $data['name'],
                    'email' => $email,
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle unique constraint violation gracefully due to race condition
                if ($e->getCode() === '23000') {
                    $existingLocation = ProspectLocation::where('email', $email)->first();
                    if ($existingLocation) {
                        return $existingLocation->prospect;
                    }
                }
                throw $e;
            }

            Log::info('New prospect lead persisted.', ['prospect_id' => $prospect->id, 'email' => $email]);

            return $prospect;
        });
    }
}
