<?php

namespace Modules\Prospects\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Prospects\Filament\Resources\Prospects\Pages\ManageProspects;
use Modules\Prospects\Filament\Resources\Prospects\ProspectResource;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\Region;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// CLA-668: sport type and federation are one dependent filter, and filters apply on change.
class ProspectFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Region $region;

    protected function setUp(): void
    {
        parent::setUp();

        $this->region = Region::query()->first() ?? Region::create(['name' => 'Antwerpen']);
    }

    private function superAdmin(): User
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');

        return $user;
    }

    private function prospect(string $type, ?string $federation, array $extra = []): Prospect
    {
        return Prospect::factory()->create(array_merge([
            'type' => $type,
            'federation' => $federation,
            'region_id' => $this->region->id,
        ], $extra));
    }

    private function page()
    {
        return Livewire::actingAs($this->superAdmin())->test(ManageProspects::class);
    }

    // S1 + S4: both conditions apply, and the old separate filters are gone.
    public function test_sport_and_federation_are_one_filter_that_applies_both_conditions(): void
    {
        $vlVv = $this->prospect('football_club', 'VL-VV');
        $frAcff = $this->prospect('football_club', 'FR-ACFF');
        $hockey = $this->prospect('hockey_club', 'VL-VHL');

        $component = $this->page()->assertTableFilterExists('sport_federation');
        $this->assertNull($component->instance()->getTable()->getFilter('type_filter'));
        $this->assertNull($component->instance()->getTable()->getFilter('federation_filter'));

        $component
            ->filterTable('sport_federation', ['type' => 'football_club', 'federation' => null])
            ->assertCanSeeTableRecords([$vlVv, $frAcff])
            ->assertCanNotSeeTableRecords([$hockey])
            ->filterTable('sport_federation', ['type' => 'football_club', 'federation' => 'VL-VV'])
            ->assertCanSeeTableRecords([$vlVv])
            ->assertCanNotSeeTableRecords([$frAcff, $hockey]);
    }

    // S1: sport options come from the data and fall back to a readable label.
    public function test_sport_options_are_the_distinct_types_in_the_data(): void
    {
        $this->prospect('table_tennis_club', 'FR-AFTT');
        $this->prospect('sports_infrastructure', 'BR-CAD');
        $this->prospect('weird_new_club', null);

        $options = ProspectResource::sportFilterOptions();

        $this->assertSame(
            ['sports_infrastructure', 'table_tennis_club', 'weird_new_club'],
            collect(array_keys($options))->sort()->values()->all(),
        );
        $this->assertSame(__('prospects::resource.options.sport_types.table_tennis_club'), $options['table_tennis_club']);
        $this->assertNotSame('prospects::resource.options.sport_types.table_tennis_club', $options['table_tennis_club']);
        $this->assertSame('Weird new club', $options['weird_new_club']);
    }

    // S2: federation options depend on the selected sport and have readable labels.
    public function test_federation_options_follow_the_selected_sport(): void
    {
        $this->prospect('football_club', 'VL-VV');
        $this->prospect('football_club', 'FR-ACFF');
        $this->prospect('football_club', null);
        $this->prospect('hockey_club', 'VL-VHL');
        $this->prospect('hockey_club', 'XX-UNKNOWN');

        $football = ProspectResource::federationFilterOptions('football_club');
        $this->assertSame(['FR-ACFF', 'VL-VV'], collect(array_keys($football))->sort()->values()->all());
        $this->assertSame('Voetbal Vlaanderen (VL-VV)', $football['VL-VV']);

        $hockey = ProspectResource::federationFilterOptions('hockey_club');
        $this->assertSame(['VL-VHL', 'XX-UNKNOWN'], collect(array_keys($hockey))->sort()->values()->all());
        $this->assertSame('XX-UNKNOWN', $hockey['XX-UNKNOWN']);

        $all = ProspectResource::federationFilterOptions(null);
        $this->assertSame(['FR-ACFF', 'VL-VHL', 'VL-VV', 'XX-UNKNOWN'], collect(array_keys($all))->sort()->values()->all());
    }

    // S3: an incompatible federation is cleared when the sport changes; a federation fills its sport.
    public function test_changing_sport_clears_incompatible_federation_and_federation_fills_sport(): void
    {
        $this->prospect('football_club', 'VL-VV');
        $this->prospect('hockey_club', 'VL-VHL');
        $this->prospect('football_club', 'SHARED');
        $this->prospect('hockey_club', 'SHARED');

        $this->page()
            ->set('tableFilters.sport_federation.type', 'football_club')
            ->set('tableFilters.sport_federation.federation', 'VL-VV')
            ->set('tableFilters.sport_federation.type', 'hockey_club')
            ->assertSet('tableFilters.sport_federation.federation', null)
            ->assertSet('tableFilters.sport_federation.type', 'hockey_club')
            // compatible federation survives a sport change
            ->set('tableFilters.sport_federation.federation', 'SHARED')
            ->set('tableFilters.sport_federation.type', 'football_club')
            ->assertSet('tableFilters.sport_federation.federation', 'SHARED');

        $this->page()
            ->set('tableFilters.sport_federation.federation', 'VL-VHL')
            ->assertSet('tableFilters.sport_federation.type', 'hockey_club');

        // ambiguous federation (two sports) leaves the sport untouched
        $this->page()
            ->set('tableFilters.sport_federation.federation', 'SHARED')
            ->assertSet('tableFilters.sport_federation.type', null);
    }

    // S4: indicators show readable labels.
    public function test_indicators_show_readable_sport_and_federation(): void
    {
        $this->prospect('football_club', 'VL-VV');

        $component = $this->page()
            ->filterTable('sport_federation', ['type' => 'football_club', 'federation' => 'VL-VV']);

        $indicators = collect($component->instance()->getTable()->getFilter('sport_federation')->getIndicators())
            ->map(fn ($i) => $i->getLabel())->implode(' | ');

        $this->assertStringContainsString(__('prospects::resource.options.sport_types.football_club'), $indicators);
        $this->assertStringContainsString('Voetbal Vlaanderen (VL-VV)', $indicators);
    }

    // S5: filters apply on change; there is no Apply action and reset clears everything.
    public function test_filters_are_not_deferred_and_reset_clears_them(): void
    {
        $football = $this->prospect('football_club', 'VL-VV');
        $hockey = $this->prospect('hockey_club', 'VL-VHL');

        $component = $this->page();

        $this->assertFalse($component->instance()->getTable()->hasDeferredFilters());
        $this->assertFalse($component->instance()->getTable()->getFiltersApplyAction()->isVisible());

        $component
            ->set('tableFilters.sport_federation.type', 'hockey_club')
            ->assertCanSeeTableRecords([$hockey])
            ->assertCanNotSeeTableRecords([$football])
            ->resetTableFilters()
            ->assertCanSeeTableRecords([$hockey, $football]);
    }

    // S5: the other filters keep working.
    public function test_has_email_and_region_filters_still_work(): void
    {
        $other = Region::create(['name' => 'CLA-668 Test Region']);

        $withEmail = $this->prospect('football_club', 'VL-VV');
        $withoutEmail = $this->prospect('football_club', 'VL-VV');
        $withoutEmail->locations()->update(['email' => null]);
        $elsewhere = $this->prospect('football_club', 'VL-VV', ['region_id' => $other->id]);

        $this->page()
            ->filterTable('has_email', true)
            ->assertCanSeeTableRecords([$withEmail])
            ->assertCanNotSeeTableRecords([$withoutEmail])
            ->resetTableFilters()
            ->filterTable('region_filter', ['region_id' => $other->id])
            ->assertCanSeeTableRecords([$elsewhere])
            ->assertCanNotSeeTableRecords([$withEmail, $withoutEmail]);
    }
}
