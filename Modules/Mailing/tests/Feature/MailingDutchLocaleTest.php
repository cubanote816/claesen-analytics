<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\CampaignStatus;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\ListCampaigns;
use Modules\Mailing\Models\Campaign;
use Modules\Mailing\Models\EmailTemplate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-693: the Dutch UI (and the Dutch user guide that quotes it) shows Dutch names.
 * The fallback locale is nl, so a key missing from nl rendered as the raw key, and enum
 * labels called without a locale were always English.
 */
class MailingDutchLocaleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, true> */
    private static function flatten(array $lines, string $prefix = ''): array
    {
        $keys = [];
        foreach ($lines as $key => $value) {
            $keys += is_array($value) ? self::flatten($value, "{$prefix}{$key}.") : ["{$prefix}{$key}" => true];
        }

        return $keys;
    }

    public function test_every_english_mailing_string_has_a_dutch_translation(): void
    {
        foreach (['resource', 'preferences'] as $file) {
            $en = self::flatten(require base_path("Modules/Mailing/lang/en/{$file}.php"));
            $nl = self::flatten(require base_path("Modules/Mailing/lang/nl/{$file}.php"));

            $this->assertSame([], array_keys(array_diff_key($en, $nl)), "{$file}: keys missing in nl");
        }
    }

    public function test_campaign_list_shows_dutch_status_and_action_labels(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('admin');
        $this->actingAs($user);

        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::DRAFT,
            'template_id' => EmailTemplate::factory()->create()->id,
        ]);

        app()->setLocale('nl');

        Livewire::test(ListCampaigns::class)
            ->assertTableActionVisible('approve', $campaign)
            ->assertSee('Concept')
            ->assertSee('Goedkeuren')
            ->assertSee('Ter beoordeling indienen')
            ->assertDontSee('mailing::resource.');
    }
}
