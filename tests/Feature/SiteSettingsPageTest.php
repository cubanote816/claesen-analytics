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
}
