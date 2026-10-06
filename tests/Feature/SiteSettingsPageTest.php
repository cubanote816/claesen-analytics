<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Clusters\Website\Pages\SiteSettingsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\Site;
use Modules\Core\Models\User;
use Modules\Website\Models\SiteSetting;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * A fixed-slot settings FORM (config('website.site_settings.allowed_keys')),
 * not a CRUD resource — see SiteSettingsPage's own docblock for the "no page
 * builder" boundary. Hardcoded to Claesen's site until Bertels has its own
 * panel/resources (F3/F4).
 */
final class SiteSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_mount_loads_existing_settings_into_the_form(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create(['key' => 'phone', 'value' => '+32 555', 'type' => SiteSetting::TYPE_TEXT]);
        SiteSetting::factory()->create([
            'key' => 'hours',
            'value' => ['nl' => 'Ma-Vr: 9-17u'],
            'type' => SiteSetting::TYPE_TRANSLATABLE,
        ]);

        Livewire::test(SiteSettingsPage::class)
            ->assertFormSet([
                'phone' => '+32 555',
                'hours' => ['nl' => 'Ma-Vr: 9-17u', 'en' => null, 'fr' => null, 'de' => null],
            ]);
    }

    public function test_mount_leaves_unset_keys_empty_instead_of_erroring(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->assertOk()
            ->assertFormSet(['phone' => null]);
    }

    public function test_saving_persists_a_site_setting_row_per_allowed_key(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'phone' => '+32 111',
                'email' => 'info@example.test',
                'address' => 'Main street 1',
                'hours' => ['nl' => 'Ma-Vr: 9-17u', 'en' => 'Mon-Fri: 9am-5pm'],
                'social_links' => [['platform' => 'facebook', 'url' => 'https://fb.example']],
            ])
            ->call('save');

        $siteId = Site::claesenId();

        $phone = SiteSetting::query()->where('site_id', $siteId)->where('key', 'phone')->firstOrFail();
        $this->assertSame('+32 111', $phone->value);

        // assertEquals, not assertSame: PHP array equality via === also
        // compares key order, which JSON round-tripping does not guarantee
        // (same convention already established in CLA-468's taxonomy tests).
        $hours = SiteSetting::query()->where('site_id', $siteId)->where('key', 'hours')->firstOrFail();
        $this->assertEquals(['nl' => 'Ma-Vr: 9-17u', 'en' => 'Mon-Fri: 9am-5pm'], $hours->value);

        $links = SiteSetting::query()->where('site_id', $siteId)->where('key', 'social_links')->firstOrFail();
        $this->assertEquals([['platform' => 'facebook', 'url' => 'https://fb.example']], $links->value);
    }

    public function test_saving_again_updates_the_same_row_instead_of_creating_a_duplicate(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create(['key' => 'phone', 'value' => '+32 111', 'type' => SiteSetting::TYPE_TEXT]);

        Livewire::test(SiteSettingsPage::class)
            ->fillForm(['phone' => '+32 999'])
            ->call('save');

        $this->assertSame(1, SiteSetting::query()->where('key', 'phone')->count());
        $this->assertSame('+32 999', SiteSetting::query()->where('key', 'phone')->firstOrFail()->value);
    }

    private function actingAsSuperAdmin(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('super_admin');

        $this->actingAs($user);
    }

    /**
     * The email field is the one that actually broke: the value saved was
     * `info@electrobertels.be5`, typed into a plain TextInput with no format rule.
     *
     * `->email()` alone does NOT reject it — a digit is legal in a TLD under RFC
     * validation, which is why the first attempt at this fix looked like it should have
     * worked and did not. The rule that catches it requires the TLD to be letters only.
     *
     * Deliberately not pretending to catch everything: `info@electrobertels.bee` is a
     * syntactically valid address, and no format rule rejects it. This guards the class
     * of mistake that happened, not every typo.
     */
    public function test_the_email_field_rejects_a_value_that_is_not_an_address(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->fillForm(['email' => 'info@electrobertels.be5'])
            ->call('save')
            ->assertHasFormErrors(['email']);

        $this->assertNotSame(
            'info@electrobertels.be5',
            SiteSetting::query()->where('key', 'email')->value('value'),
        );
    }

    /**
     * The address is edited as four fields, not as raw JSON in a single-line input.
     *
     * That input is where `"Balennn"` came from: typing inside a JSON blob produces
     * still-valid JSON, so nothing stopped it. With parts, a mistake is local and
     * visible instead of silently rewriting the whole object.
     *
     * Compared with the keys sorted, because MySQL's JSON column reorders object keys by
     * length — asserting the order here would be asserting the database's, not the page's.
     */
    public function test_the_address_is_stored_as_the_contract_shape_and_drops_other_keys(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'address_structured' => [
                    'street' => 'Benoit Jansenstraat 4',
                    'postal_code' => '2490',
                    'city' => 'Balen',
                    'country_code' => 'BE',
                    // Not part of the contract: it must not reach the column, or it would
                    // travel into the site's snapshot untouched.
                    'stray' => 'x',
                ],
            ])
            ->call('save');

        $stored = SiteSetting::query()->where('key', 'address_structured')->value('value');

        ksort($stored);

        $this->assertSame([
            'city' => 'Balen',
            'country_code' => 'BE',
            'postal_code' => '2490',
            'street' => 'Benoit Jansenstraat 4',
        ], $stored);
    }

    /**
     * `opening_hours` is edited as one row per day, not as raw JSON in a single-line input.
     *
     * That input is the same defect that produced `"Balennn"` in the address: a JSON blob
     * whose only validation was `rule('json')`, and a typo inside a blob stays valid JSON.
     * Here the contract is a four-key day list in which `hours === null` IS "closed", and
     * the sync validator only accepts those four keys — a seven-day list is what got the
     * first seeder rejected.
     *
     * Compared with assertEquals, not assertSame: MySQL reorders object keys by length, so
     * asserting order would be asserting the database's, not the page's.
     */
    public function test_opening_hours_round_trips_the_contract_shape(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'opening_hours' => [
                    ['day_key' => 'sunday', 'closed' => true],
                    ['day_key' => 'monday', 'closed' => true],
                    ['day_key' => 'tuesdayToFriday', 'closed' => false, 'hours' => [
                        ['open' => '09:00', 'close' => '12:00'],
                        ['open' => '13:00', 'close' => '18:00'],
                    ]],
                    ['day_key' => 'saturday', 'closed' => false, 'hours' => [
                        ['open' => '09:00', 'close' => '12:00'],
                    ]],
                ],
            ])
            ->call('save');

        $this->assertEquals([
            ['day_key' => 'sunday', 'hours' => null],
            ['day_key' => 'monday', 'hours' => null],
            ['day_key' => 'tuesdayToFriday', 'hours' => [
                ['open' => '09:00', 'close' => '12:00'],
                ['open' => '13:00', 'close' => '18:00'],
            ]],
            ['day_key' => 'saturday', 'hours' => [
                ['open' => '09:00', 'close' => '12:00'],
            ]],
        ], SiteSetting::query()->where('key', 'opening_hours')->value('value'));
    }

    /**
     * The lesson this test exists for. The previous attempt blamed the mapper for a column
     * that came out null, when the cause was the test itself: it pushed a deliberately
     * invalid `day_key` (`everyFullMoon`) into the form, the `Select` rejected it, and
     * validation aborted the whole save — which is correct. The save never ran.
     *
     * So this asserts the observable outcome (nothing is written) and says out loud that
     * the `Select` is what stops it, instead of claiming to test the mapper's internals —
     * and it asserts the exact error path, so it proves the refusal came from the day
     * field and not from something unrelated breaking the save.
     */
    public function test_opening_hours_never_persists_a_day_outside_the_contract(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'opening_hours' => [
                    ['day_key' => 'everyFullMoon', 'closed' => false, 'hours' => [
                        ['open' => '09:00', 'close' => '12:00'],
                    ]],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['opening_hours.0.day_key']);

        $this->assertNull(
            SiteSetting::query()->where('key', 'opening_hours')->value('value'),
            'A day the contract does not define must not reach the column.',
        );
    }

    /**
     * `hours: null` IS "closed" in the contract, so the toggle decides and not whatever
     * ranges happen to be left in the state. Without this, flipping a day to closed could
     * keep serving its old hours.
     */
    public function test_opening_hours_stores_a_closed_day_as_null_hours(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'opening_hours' => [
                    ['day_key' => 'sunday', 'closed' => true, 'hours' => [
                        ['open' => '09:00', 'close' => '12:00'],
                    ]],
                ],
            ])
            ->call('save');

        $this->assertEquals(
            [['day_key' => 'sunday', 'hours' => null]],
            SiteSetting::query()->where('key', 'opening_hours')->value('value'),
        );
    }

    /**
     * The same day twice must not be storable. Without the `Select`'s `distinct()` the save
     * would succeed and the snapshot would carry two Mondays, which is not a state the
     * contract describes: its day list is a set.
     *
     * The failure lands on the duplicated row, so asserting the second row's error path is
     * the honest one — and the column staying null is what proves the save was refused
     * rather than half-applied.
     */
    public function test_opening_hours_rejects_the_same_day_twice(): void
    {
        $this->actingAsSuperAdmin();

        Livewire::test(SiteSettingsPage::class)
            ->fillForm([
                'opening_hours' => [
                    ['day_key' => 'monday', 'closed' => false, 'hours' => [['open' => '09:00', 'close' => '12:00']]],
                    ['day_key' => 'monday', 'closed' => false, 'hours' => [['open' => '13:00', 'close' => '18:00']]],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors(['opening_hours.1.day_key']);

        $this->assertNull(SiteSetting::query()->where('key', 'opening_hours')->value('value'));
    }

    /**
     * A closed day still arrives with one blank period, so flipping the toggle open leaves the
     * user a row to fill instead of an empty area behind an "add period" button.
     *
     * `defaultItems` cannot cover this: by the time a day is opened, the repeater already has
     * state. Verified on the mounted form state rather than by clicking, because the toggle is
     * Filament's `visible()` reacting to `live()`, and that reaction is what this state feeds.
     */
    public function test_opening_hours_offers_a_blank_period_for_a_closed_day(): void
    {
        $this->actingAsSuperAdmin();
        SiteSetting::factory()->create([
            'key' => 'opening_hours',
            'type' => SiteSetting::TYPE_JSON,
            'value' => [['day_key' => 'sunday', 'hours' => null]],
        ]);

        $rows = array_values(Livewire::test(SiteSettingsPage::class)->get('data.opening_hours'));

        // Read through array_values() on both levels on purpose: Filament keys the state of a
        // Repeater by item uuid, not by index. Asserting `[0 => …]` here fails against a uuid
        // key and looks like a mapping bug, which is the wrong conclusion.
        $this->assertCount(1, $rows);
        $this->assertSame('sunday', $rows[0]['day_key']);
        $this->assertTrue($rows[0]['closed']);
        $this->assertSame(
            [['open' => null, 'close' => null]],
            array_values($rows[0]['hours']),
        );
    }
}
