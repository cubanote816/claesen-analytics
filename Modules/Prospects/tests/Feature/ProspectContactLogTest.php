<?php

namespace Modules\Prospects\Tests\Feature;

use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Prospects\Enums\ContactDirection;
use Modules\Prospects\Enums\ContactMethod;
use Modules\Prospects\Filament\Resources\Prospects\Pages\ManageProspects;
use Modules\Prospects\Filament\Resources\Prospects\ProspectResource;
use Modules\Prospects\Models\ContactLog;
use Modules\Prospects\Models\Prospect;
use Modules\Prospects\Models\Region;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

// CLA-711 (S2-S5): manual contact history on prospects.
class ProspectContactLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Region::query()->first() ?? Region::create(['name' => 'Antwerpen']);
    }

    private function userWithRole(string $role): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function page(User $user)
    {
        return Livewire::actingAs($user)->test(ManageProspects::class);
    }

    private function log(Prospect $prospect, string $contactedAt, array $extra = []): ContactLog
    {
        return ContactLog::factory()->create(array_merge([
            'prospect_id' => $prospect->id,
            'contacted_at' => $contactedAt,
        ], $extra));
    }

    private function validData(array $override = []): array
    {
        return array_merge([
            'contacted_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'direction' => 'outbound',
            'method' => 'phone',
            'note' => 'Called the secretary, asked for a quote.',
        ], $override);
    }

    // S2 + S3
    public function test_admin_logs_a_contact_with_all_fields(): void
    {
        $admin = $this->userWithRole('admin');
        $prospect = Prospect::factory()->create();

        $this->page($admin)
            ->callAction(TestAction::make('mark_contacted')->table($prospect), $this->validData())
            ->assertHasNoFormErrors();

        $this->assertSame(1, ContactLog::count());
        $row = ContactLog::first();
        $this->assertSame($prospect->id, $row->prospect_id);
        $this->assertSame($admin->id, $row->user_id);
        $this->assertSame(ContactDirection::Outbound, $row->direction);
        $this->assertSame(ContactMethod::Phone, $row->method);
        $this->assertSame('Called the secretary, asked for a quote.', $row->note);
        $this->assertSame(now()->subHour()->format('Y-m-d H'), $row->contacted_at->format('Y-m-d H'));
    }

    public function test_super_admin_logs_a_contact_and_note_is_optional(): void
    {
        $user = $this->userWithRole('super_admin');
        $prospect = Prospect::factory()->create();

        $this->page($user)
            ->callAction(TestAction::make('mark_contacted')->table($prospect), $this->validData(['direction' => 'inbound', 'method' => 'website_form', 'note' => null]))
            ->assertHasNoFormErrors();

        $row = ContactLog::first();
        $this->assertSame(ContactDirection::Inbound, $row->direction);
        $this->assertSame(ContactMethod::WebsiteForm, $row->method);
        $this->assertNull($row->note);
        $this->assertSame($user->id, $row->user_id);
    }

    public static function invalidInputs(): array
    {
        return [
            'future date' => [['contacted_at' => '2099-01-01 10:00:00'], 'contacted_at'],
            'invalid method' => [['method' => 'carrier_pigeon'], 'method'],
            'invalid direction' => [['direction' => 'sideways'], 'direction'],
            'note too long' => [['note' => str_repeat('x', 1001)], 'note'],
            'missing date' => [['contacted_at' => null], 'contacted_at'],
        ];
    }

    /** @dataProvider invalidInputs */
    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_and_nothing_is_stored(array $override, string $field): void
    {
        $prospect = Prospect::factory()->create();

        $this->page($this->userWithRole('admin'))
            ->callAction(TestAction::make('mark_contacted')->table($prospect), $this->validData($override))
            ->assertHasFormErrors([$field]);

        $this->assertSame(0, ContactLog::count());
    }

    public function test_note_of_exactly_1000_characters_is_accepted(): void
    {
        $prospect = Prospect::factory()->create();

        $this->page($this->userWithRole('admin'))
            ->callAction(TestAction::make('mark_contacted')->table($prospect), $this->validData(['note' => str_repeat('x', 1000)]))
            ->assertHasNoFormErrors();

        $this->assertSame(1, ContactLog::count());
    }

    // S3
    public static function nonAdminRoles(): array
    {
        return [['viewer'], ['financial_manager']];
    }

    /** @dataProvider nonAdminRoles */
    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_do_not_see_contact_actions_and_cannot_log(string $role): void
    {
        $prospect = Prospect::factory()->create();
        $this->log($prospect, '2026-05-01 10:00:00');

        $component = $this->page($this->userWithRole($role))
            ->assertActionHidden(TestAction::make('mark_contacted')->table($prospect))
            ->assertActionHidden(TestAction::make('remove_contact')->table($prospect));

        try {
            $component->callAction(TestAction::make('mark_contacted')->table($prospect), $this->validData());
        } catch (\Throwable) {
            // refusal is acceptable; what matters is what is stored
        }

        $this->assertSame(1, ContactLog::count());
    }

    public function test_technician_cannot_access_the_prospects_resource(): void
    {
        $this->actingAs($this->userWithRole('technician'));

        $this->assertFalse(ProspectResource::canAccess());
    }

    // S4
    public function test_last_contact_column_shows_the_most_recent_date_and_is_sortable(): void
    {
        $contacted = Prospect::factory()->create();
        $never = Prospect::factory()->create();
        $this->log($contacted, '2026-03-01 09:00:00');
        $this->log($contacted, '2026-06-15 14:30:00');
        $this->log($contacted, '2026-04-10 08:00:00');

        $this->page($this->userWithRole('admin'))
            ->assertTableColumnExists('contact_logs_max_contacted_at')
            ->assertCanRenderTableColumn('contact_logs_max_contacted_at')
            ->assertTableColumnStateSet('contact_logs_max_contacted_at', '2026-06-15 14:30:00', $contacted)
            ->assertTableColumnStateSet('contact_logs_max_contacted_at', null, $never)
            ->sortTable('contact_logs_max_contacted_at', 'desc')
            ->assertCanSeeTableRecords([$contacted, $never], inOrder: true);
    }

    public function test_contacted_filter_splits_prospects(): void
    {
        $contacted = Prospect::factory()->create();
        $notContacted = Prospect::factory()->create();
        $this->log($contacted, '2026-05-01 10:00:00');

        $this->page($this->userWithRole('admin'))
            ->filterTable('contacted', true)
            ->assertCanSeeTableRecords([$contacted])
            ->assertCanNotSeeTableRecords([$notContacted])
            ->filterTable('contacted', false)
            ->assertCanSeeTableRecords([$notContacted])
            ->assertCanNotSeeTableRecords([$contacted])
            ->removeTableFilter('contacted')
            ->assertCanSeeTableRecords([$contacted, $notContacted]);
    }

    // S5
    public function test_remove_contact_deletes_only_the_chosen_entry(): void
    {
        $prospect = Prospect::factory()->create();
        $other = Prospect::factory()->create();
        $keep = $this->log($prospect, '2026-05-01 10:00:00');
        $remove = $this->log($prospect, '2026-05-02 10:00:00');
        $otherProspectLog = $this->log($other, '2026-05-03 10:00:00');

        $this->page($this->userWithRole('admin'))
            ->callAction(TestAction::make('remove_contact')->table($prospect), ['contact_log_id' => $remove->id])
            ->assertHasNoFormErrors();

        $this->assertNull(ContactLog::find($remove->id));
        $this->assertNotNull(ContactLog::find($keep->id));
        $this->assertNotNull(ContactLog::find($otherProspectLog->id));
        $this->assertSame(2, ContactLog::count());
    }

    public function test_remove_contact_rejects_an_entry_of_another_prospect(): void
    {
        $prospect = Prospect::factory()->create();
        $other = Prospect::factory()->create();
        $this->log($prospect, '2026-05-01 10:00:00');
        $foreign = $this->log($other, '2026-05-03 10:00:00');

        $component = $this->page($this->userWithRole('admin'));

        try {
            $component->callAction(TestAction::make('remove_contact')->table($prospect), ['contact_log_id' => $foreign->id]);
        } catch (\Throwable) {
            // refusal is acceptable; what matters is what survives
        }

        $this->assertNotNull(ContactLog::find($foreign->id));
        $this->assertSame(2, ContactLog::count());
    }

    public function test_remove_contact_is_hidden_when_the_prospect_has_no_entries(): void
    {
        $prospect = Prospect::factory()->create();

        $this->page($this->userWithRole('admin'))
            ->assertActionHidden(TestAction::make('remove_contact')->table($prospect))
            ->assertActionVisible(TestAction::make('mark_contacted')->table($prospect));
    }

    // S4: View modal
    private function viewModalHtml(Prospect $prospect, User $user): string
    {
        $component = $this->page($user)->mountAction(TestAction::make('view')->table($prospect));
        $page = $component->instance();
        $method = new \ReflectionMethod($page, 'getMountedActionSchema');
        $method->setAccessible(true);

        return $method->invoke($page)->toHtml();
    }

    public function test_view_modal_shows_contact_history_newest_first(): void
    {
        $prospect = Prospect::factory()->create();
        $this->log($prospect, '2026-01-01 10:00:00', ['note' => 'OLDEST-NOTE']);
        $this->log($prospect, '2026-09-01 10:00:00', ['note' => 'NEWEST-NOTE']);

        $html = $this->viewModalHtml($prospect, $this->userWithRole('admin'));

        $this->assertStringContainsString(__('prospects::resource.sections.contact_history'), $html);
        $this->assertStringContainsString('NEWEST-NOTE', $html);
        $this->assertStringContainsString('OLDEST-NOTE', $html);
        $this->assertLessThan(strpos($html, 'OLDEST-NOTE'), strpos($html, 'NEWEST-NOTE'));
        $this->assertStringNotContainsString(__('prospects::resource.contact_history.empty'), $html);
    }

    public function test_view_modal_shows_an_empty_state_without_entries(): void
    {
        $prospect = Prospect::factory()->create();

        $html = $this->viewModalHtml($prospect, $this->userWithRole('admin'));

        $this->assertStringContainsString(__('prospects::resource.contact_history.empty'), $html);
    }

    // S5 regression
    public function test_existing_actions_are_untouched(): void
    {
        $prospect = Prospect::factory()->create();

        $this->page($this->userWithRole('super_admin'))
            ->assertActionVisible(TestAction::make('view')->table($prospect))
            ->assertActionVisible(TestAction::make('edit')->table($prospect))
            ->assertActionVisible(TestAction::make('delete')->table($prospect))
            ->assertActionExists(TestAction::make('execute_campaign')->table()->bulk());
    }
}
