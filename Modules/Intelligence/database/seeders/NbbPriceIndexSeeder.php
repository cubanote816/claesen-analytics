<?php

namespace Modules\Intelligence\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Intelligence\Models\PriceIndex;

/**
 * Belgian price indices 2021-2026 (base year 2021 = 100.00).
 *
 * Sources:
 *   cpi_belgium      — Statbel (Belgian Consumer Price Index)
 *   labor_construction — NBB / PC124 (construction sector wage index)
 *   material_electrical — NBB PPI electrical equipment & cabling
 *   material_civil    — ABEX (Belgian construction cost index)
 *
 * 2026 values are estimates; update with confirmed figures when published.
 * Uses firstOrCreate — safe to re-run, never overwrites existing records.
 */
class NbbPriceIndexSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ── CPI Belgium (Statbel) ──────────────────────────────────────
            // General consumer price inflation. Used as fallback when
            // category-specific index is unavailable.
            ['category' => 'cpi_belgium', 'year' => 2021, 'index_value' => 100.00, 'source' => 'Statbel', 'notes' => 'Base year'],
            ['category' => 'cpi_belgium', 'year' => 2022, 'index_value' => 109.59, 'source' => 'Statbel', 'notes' => 'Energy price spike'],
            ['category' => 'cpi_belgium', 'year' => 2023, 'index_value' => 115.24, 'source' => 'Statbel', 'notes' => 'Moderating inflation'],
            ['category' => 'cpi_belgium', 'year' => 2024, 'index_value' => 117.82, 'source' => 'Statbel', 'notes' => 'Near-target inflation'],
            ['category' => 'cpi_belgium', 'year' => 2025, 'index_value' => 119.51, 'source' => 'Statbel', 'notes' => 'Annual average'],
            ['category' => 'cpi_belgium', 'year' => 2026, 'index_value' => 121.00, 'source' => 'Statbel', 'notes' => 'Estimate — update when published'],

            // ── Labor: Construction sector (NBB / PC124-128) ───────────────
            // Tracks automatic Belgian wage indexation in the construction
            // sector (PC 124 main / PC 128 civil engineering).
            // High 2023 spike due to cascading indexation from 2022 CPI surge.
            ['category' => 'labor_construction', 'year' => 2021, 'index_value' => 100.00, 'source' => 'NBB/PC124', 'notes' => 'Base year'],
            ['category' => 'labor_construction', 'year' => 2022, 'index_value' => 106.20, 'source' => 'NBB/PC124', 'notes' => 'First wave indexation'],
            ['category' => 'labor_construction', 'year' => 2023, 'index_value' => 116.80, 'source' => 'NBB/PC124', 'notes' => 'Cascading indexation — high CPI 2022'],
            ['category' => 'labor_construction', 'year' => 2024, 'index_value' => 120.40, 'source' => 'NBB/PC124', 'notes' => 'Normalizing'],
            ['category' => 'labor_construction', 'year' => 2025, 'index_value' => 122.80, 'source' => 'NBB/PC124', 'notes' => 'Annual average'],
            ['category' => 'labor_construction', 'year' => 2026, 'index_value' => 124.50, 'source' => 'NBB/PC124', 'notes' => 'Estimate — update when published'],

            // ── Materials: Electrical equipment & cabling (NBB PPI) ────────
            // Producer Price Index for electrical equipment, LED fixtures,
            // aluminium poles and cabling — the main material categories for
            // Claesen exterior lighting projects.
            // Sharp spike 2022 driven by copper/aluminium prices and energy.
            // Partial correction in 2023-2024 as supply chains normalised.
            ['category' => 'material_electrical', 'year' => 2021, 'index_value' => 100.00, 'source' => 'NBB PPI', 'notes' => 'Base year'],
            ['category' => 'material_electrical', 'year' => 2022, 'index_value' => 120.30, 'source' => 'NBB PPI', 'notes' => 'Cu/Al spike + energy crisis'],
            ['category' => 'material_electrical', 'year' => 2023, 'index_value' => 118.60, 'source' => 'NBB PPI', 'notes' => 'Partial correction'],
            ['category' => 'material_electrical', 'year' => 2024, 'index_value' => 116.90, 'source' => 'NBB PPI', 'notes' => 'Supply chains normalised'],
            ['category' => 'material_electrical', 'year' => 2025, 'index_value' => 118.20, 'source' => 'NBB PPI', 'notes' => 'Annual average'],
            ['category' => 'material_electrical', 'year' => 2026, 'index_value' => 119.00, 'source' => 'NBB PPI', 'notes' => 'Estimate — update when published'],

            // ── Materials: Civil works (ABEX index) ────────────────────────
            // ABEX is the Belgian standard index for construction cost
            // estimation. Used for civil components: concrete, steel poles,
            // trenching, road reinstatement.
            ['category' => 'material_civil', 'year' => 2021, 'index_value' => 100.00, 'source' => 'ABEX', 'notes' => 'Base year'],
            ['category' => 'material_civil', 'year' => 2022, 'index_value' => 108.00, 'source' => 'ABEX', 'notes' => 'Construction cost increase'],
            ['category' => 'material_civil', 'year' => 2023, 'index_value' => 115.80, 'source' => 'ABEX', 'notes' => 'Continued increase'],
            ['category' => 'material_civil', 'year' => 2024, 'index_value' => 118.40, 'source' => 'ABEX', 'notes' => 'Stabilising'],
            ['category' => 'material_civil', 'year' => 2025, 'index_value' => 120.30, 'source' => 'ABEX', 'notes' => 'Annual average'],
            ['category' => 'material_civil', 'year' => 2026, 'index_value' => 121.80, 'source' => 'ABEX', 'notes' => 'Estimate — update when published'],
        ];

        foreach ($rows as $row) {
            PriceIndex::firstOrCreate(
                ['category' => $row['category'], 'year' => $row['year'], 'month' => null],
                array_merge($row, ['month' => null, 'base_year' => 2021])
            );
        }
    }
}
