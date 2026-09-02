<?php

namespace Modules\Mailing\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Modules\Core\Models\User;
use Modules\Mailing\Enums\AudienceType;
use Modules\Mailing\Filament\Resources\CampaignResource;
use Modules\Mailing\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use Modules\Mailing\Models\EmailTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * CLA-529 — CampaignForm's scheduled_at picker dehydrates the operator's
 * Europe/Brussels wall-clock input to a UTC string before it is persisted
 * (CampaignForm::configure -> DateTimePicker::dehydrateStateUsing). The raw
 * column value must be UTC, checked with DB::table(...) rather than the Eloquent
 * 'datetime' cast (which would re-apply the app timezone on read and mask a bug).
 */
class CampaignFormScheduledAtTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string,array{0:string,1:string,2:string}> */
    public static function brusselsToUtcProvider(): array
    {
        return [
            // frozen "now" (before the scheduled date), Brussels input, expected raw UTC
            'summer / CEST (UTC+2)' => ['2026-07-10 08:00:00', '2026-07-15 14:00:00', '2026-07-15 12:00:00'],
            'winter / CET (UTC+1)' => ['2026-01-10 08:00:00', '2026-01-15 14:00:00', '2026-01-15 13:00:00'],
        ];
    }

    #[DataProvider('brusselsToUtcProvider')]
    public function test_scheduled_at_is_stored_as_raw_utc(string $frozenNow, string $brusselsInput, string $expectedUtcRaw): void
    {
        Carbon::setTestNow(Carbon::parse($frozenNow, 'Europe/Brussels'));

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Boot the panel through the middleware stack so Filament's form-testing
        // helpers have a current panel / testing schema context.
        $this->get(CampaignResource::getUrl('create'))->assertSuccessful();

        $template = EmailTemplate::factory()->create();
        $description = 'CLA-529 tz roundtrip '.$frozenNow;

        Livewire::test(CreateCampaign::class)
            ->fillForm([
                'template_id' => $template->id,
                'template_name' => $template->name,
                'description' => $description,
                'subject_snapshot' => 'Subject',
                'body_snapshot' => 'Body',
                'audience_type' => AudienceType::ALL_SUBSCRIBED->value,
                'scheduled_at' => $brusselsInput,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $raw = DB::table('mailing_campaigns')->where('description', $description)->value('scheduled_at');

        $this->assertSame($expectedUtcRaw, $raw);
    }
}
