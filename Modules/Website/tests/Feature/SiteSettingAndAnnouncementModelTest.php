<?php

declare(strict_types=1);

namespace Modules\Website\Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Site;
use Modules\Website\Models\Announcement;
use Modules\Website\Models\SiteSetting;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Tests\TestCase;

/**
 * F3/CLA-469 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Model-level coverage not already exercised by SiteContentApiTest's HTTP
 * assertions: the config-driven key allowlist, the resolvedValue() fallback
 * chain, audit logging (LogsActivity, per ConsultationRequest's pattern),
 * and the site_id foreign key.
 */
final class SiteSettingAndAnnouncementModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_keys_come_from_config_not_a_hardcoded_list(): void
    {
        config(['website.site_settings.allowed_keys' => ['custom_key' => SiteSetting::TYPE_TEXT]]);

        $this->assertTrue(SiteSetting::isAllowedKey('custom_key'));
        $this->assertFalse(SiteSetting::isAllowedKey('phone'));
        $this->assertSame(SiteSetting::TYPE_TEXT, SiteSetting::typeForKey('custom_key'));
        $this->assertNull(SiteSetting::typeForKey('phone'));
    }

    public function test_resolved_value_falls_back_to_fallback_locale_then_first_available(): void
    {
        $setting = SiteSetting::factory()->create([
            'key' => 'hours',
            'value' => ['fr' => 'Lu-Ve: 9-17h'],
            'type' => SiteSetting::TYPE_TRANSLATABLE,
        ]);

        app()->setLocale('de'); // not present, app.fallback_locale ('nl') also absent
        // ...so it falls all the way through to the first available translation.
        $this->assertSame('Lu-Ve: 9-17h', $setting->resolvedValue());

        app()->setLocale('fr');
        $this->assertSame('Lu-Ve: 9-17h', $setting->resolvedValue());
    }

    public function test_resolved_value_returns_the_raw_scalar_for_non_translatable_types(): void
    {
        $text = SiteSetting::factory()->create(['key' => 'email', 'value' => 'info@example.test', 'type' => SiteSetting::TYPE_TEXT]);
        $json = SiteSetting::factory()->create([
            'key' => 'social_links',
            'value' => [['platform' => 'facebook', 'url' => 'https://fb.example']],
            'type' => SiteSetting::TYPE_JSON,
        ]);

        $this->assertSame('info@example.test', $text->resolvedValue());
        $this->assertSame([['platform' => 'facebook', 'url' => 'https://fb.example']], $json->resolvedValue());
    }

    public function test_both_models_use_logs_activity_for_audit(): void
    {
        $this->assertContains(LogsActivity::class, class_uses_recursive(SiteSetting::class));
        $this->assertContains(LogsActivity::class, class_uses_recursive(Announcement::class));
    }

    public function test_site_setting_and_announcement_default_to_the_claesen_site(): void
    {
        $setting = SiteSetting::factory()->create();
        $announcement = Announcement::factory()->create();

        $this->assertSame(Site::claesenId(), $setting->site_id);
        $this->assertSame(Site::claesenId(), $announcement->site_id);
    }

    public function test_site_id_is_a_real_foreign_key_that_blocks_deleting_a_referenced_site(): void
    {
        $site = Site::factory()->create();
        SiteSetting::factory()->create(['site_id' => $site->id]);

        $this->expectException(QueryException::class);

        $site->delete();
    }
}
