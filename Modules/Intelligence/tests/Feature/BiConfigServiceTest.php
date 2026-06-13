<?php

namespace Modules\Intelligence\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Database\Seeders\BiConfigSeeder;
use Modules\Intelligence\Models\BiConfig;
use Modules\Intelligence\Services\BiConfigService;
use Tests\TestCase;

class BiConfigServiceTest extends TestCase
{
    use RefreshDatabase;

    private BiConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BiConfigSeeder::class);
        $this->service = app(BiConfigService::class);
    }

    // -------------------------------------------------------------------------
    // Seeder
    // -------------------------------------------------------------------------

    public function test_seeder_creates_all_five_entries(): void
    {
        $keys = BiConfig::pluck('config_key')->all();

        $this->assertCount(5, $keys);
        $this->assertEqualsCanonicalizing([
            'project_type_labels',
            'estimate_status_labels',
            'variant_margin_targets',
            'labor_sync_schedule',
            'billing_guardian_rules',
        ], $keys);
    }

    public function test_seeder_does_not_overwrite_management_edits_on_rerun(): void
    {
        $this->service->set('variant_margin_targets', [
            'economy' => 18, 'standard' => 25, 'premium' => 40,
        ]);

        $this->seed(BiConfigSeeder::class);

        $this->assertSame(18, $this->service->get('variant_margin_targets.economy'));
        $this->assertSame(40, $this->service->get('variant_margin_targets.premium'));
    }

    public function test_seeder_billing_guardian_defaults(): void
    {
        $rules = $this->service->get('billing_guardian_rules');

        $this->assertSame(30, $rules['days_without_invoice']);
        $this->assertSame(500, $rules['min_amount']);
        $this->assertSame(500, $rules['min_cost_amount']);
        $this->assertFalse($rules['include_projects_without_contract']);
    }

    // -------------------------------------------------------------------------
    // get()
    // -------------------------------------------------------------------------

    public function test_get_returns_full_value_for_plain_key(): void
    {
        $margins = $this->service->get('variant_margin_targets');

        $this->assertIsArray($margins);
        $this->assertSame(20, $margins['economy']);
        $this->assertSame(27, $margins['standard']);
        $this->assertSame(35, $margins['premium']);
    }

    public function test_get_supports_dot_notation_for_nested_values(): void
    {
        $this->assertSame(27, $this->service->get('variant_margin_targets.standard'));
        $this->assertSame(30, $this->service->get('billing_guardian_rules.days_without_invoice'));
    }

    public function test_get_returns_default_for_missing_key(): void
    {
        $this->assertSame('fallback', $this->service->get('does_not_exist', 'fallback'));
        $this->assertNull($this->service->get('does_not_exist'));
    }

    public function test_get_returns_default_for_missing_subpath(): void
    {
        $this->assertSame(99, $this->service->get('variant_margin_targets.nonexistent', 99));
    }

    // -------------------------------------------------------------------------
    // set() + cache invalidation
    // -------------------------------------------------------------------------

    public function test_set_persists_and_invalidates_cache(): void
    {
        // Prime the cache
        $this->assertSame(30, $this->service->get('billing_guardian_rules.days_without_invoice'));

        $this->service->set('billing_guardian_rules', [
            'days_without_invoice'              => 45,
            'min_amount'                        => 750,
            'min_cost_amount'                   => 500,
            'include_projects_without_contract' => true,
        ]);

        // Read-after-write must reflect the new value (cache must have been forgotten)
        $this->assertSame(45, $this->service->get('billing_guardian_rules.days_without_invoice'));
        $this->assertSame(750, $this->service->get('billing_guardian_rules.min_amount'));
        $this->assertTrue($this->service->get('billing_guardian_rules.include_projects_without_contract'));
    }

    public function test_set_records_updated_by(): void
    {
        $this->service->set('labor_sync_schedule', ['start' => '22:00', 'end' => '06:00'], 42);

        $this->assertSame(42, BiConfig::where('config_key', 'labor_sync_schedule')->value('updated_by'));
    }

    // -------------------------------------------------------------------------
    // all() / flush()
    // -------------------------------------------------------------------------

    public function test_all_returns_collection_keyed_by_config_key(): void
    {
        $all = $this->service->all();

        $this->assertCount(5, $all);
        $this->assertTrue($all->has('billing_guardian_rules'));
        $this->assertSame('Monthly Billing Guardian rules', $all['billing_guardian_rules']->label);
    }

    public function test_flush_clears_cached_entries(): void
    {
        // Prime cache, then mutate the row directly (bypassing set())
        $this->service->get('variant_margin_targets');
        BiConfig::where('config_key', 'variant_margin_targets')
            ->update(['config_value' => json_encode(['economy' => 1, 'standard' => 2, 'premium' => 3])]);

        // Cache still serves the stale value
        $this->assertSame(20, $this->service->get('variant_margin_targets.economy'));

        $this->service->flush();

        $this->assertSame(1, $this->service->get('variant_margin_targets.economy'));
    }
}
