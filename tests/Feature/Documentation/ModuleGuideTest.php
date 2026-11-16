<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Support\Documentation\ModuleGuide;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * CLA-693: module user guides take UI names from the same translation keys the screens use,
 * carry stable anchors and fall back to English for locales without a guide file.
 */
class ModuleGuideTest extends TestCase
{
    private function fixture(): string
    {
        return base_path('tests/Fixtures/documentation/guide');
    }

    public function test_placeholders_render_the_exact_ui_labels(): void
    {
        $guide = ModuleGuide::render($this->fixture(), 'en');

        $this->assertStringContainsString('<span class="doc-ui doc-ui--nav">Mailing → Campaigns</span>', $guide->html);
        $this->assertStringContainsString('<span class="doc-ui doc-ui--action">New Campaign</span>', $guide->html);
        $this->assertStringContainsString('<span class="doc-ui doc-ui--label">Subject</span>', $guide->html);
        $this->assertStringContainsString('<span class="doc-ui doc-ui--action">Send now</span>', $guide->html);
        $this->assertStringContainsString('<span class="doc-ui doc-ui--enum">Draft</span>', $guide->html);
    }

    public function test_placeholders_inside_table_cells_keep_the_table(): void
    {
        $html = ModuleGuide::render($this->fixture(), 'en')->html;

        $this->assertMatchesRegularExpression('/<td><span class="doc-ui doc-ui--action">Send now<\/span><\/td>\s*<td>Sends now<\/td>/', $html);
    }

    public function test_labels_follow_the_requested_locale(): void
    {
        $guide = ModuleGuide::render($this->fixture(), 'nl');

        $this->assertSame('nl', $guide->locale);
        $this->assertSame('Fixture-handleiding', $guide->title);
        $this->assertStringContainsString('<span class="doc-ui doc-ui--action">Nu verzenden</span>', $guide->html);
        $this->assertStringContainsString('<span class="doc-ui doc-ui--action">Goedkeuren</span>', $guide->html);
        $this->assertStringContainsString('<span class="doc-ui doc-ui--enum">Concept</span>', $guide->html);
    }

    public function test_locales_without_a_guide_fall_back_to_english(): void
    {
        foreach (['fr', 'de', 'fr_BE'] as $locale) {
            $guide = ModuleGuide::render($this->fixture(), $locale);

            $this->assertSame('en', $guide->locale, $locale);
            $this->assertSame('Fixture guide', $guide->title, $locale);
        }
    }

    public function test_labels_follow_the_ui_locale_even_when_the_text_falls_back(): void
    {
        $html = ModuleGuide::render($this->fixture(), 'fr')->html;

        $this->assertStringContainsString(
            '<span class="doc-ui doc-ui--action">' . e(__('mailing::resource.actions.send_now', [], 'fr')) . '</span>',
            $html,
        );
    }

    public function test_unknown_keys_are_reported_and_shown_as_missing(): void
    {
        $guide = ModuleGuide::render($this->fixture(), 'en');

        $this->assertSame([
            'mailing::resource.actions.does_not_exist',
            'Modules\\Mailing\\Enums\\CampaignStatus::nope',
        ], $guide->missingKeys);
        $this->assertStringContainsString(
            '<span class="doc-ui doc-ui--missing">mailing::resource.actions.does_not_exist</span>',
            $guide->html,
        );
        $this->assertSame([], ModuleGuide::render($this->fixture(), 'nl')->missingKeys);
    }

    public function test_headings_get_stable_anchors_and_a_table_of_contents(): void
    {
        $guide = ModuleGuide::render($this->fixture(), 'en');

        $this->assertSame(['start', 'action-send'], $guide->anchors());
        $this->assertSame([
            ['id' => 'start', 'text' => 'Start here', 'level' => 2],
            ['id' => 'action-send', 'text' => 'Send it', 'level' => 3],
        ], $guide->toc);
        $this->assertStringContainsString('<a href="#action-send">jump to sending</a>', $guide->html);
    }

    public function test_front_matter_is_exposed_for_navigation_and_future_indexing(): void
    {
        $this->assertSame(
            ['module' => 'Fixture', 'title' => 'Fixture guide', 'navigation_label' => 'Fixture'],
            ModuleGuide::meta($this->fixture(), 'en'),
        );
        $this->assertSame(['en', 'nl'], ModuleGuide::locales($this->fixture()));
    }

    public function test_guide_security_boundaries(): void
    {
        $dir = sys_get_temp_dir() . '/guide-security-' . bin2hex(random_bytes(8));
        mkdir($dir);

        try {
            Lang::addLines(['documentation.probe' => '<img src=x onerror=alert(1)>'], 'en');
            file_put_contents($dir . '/en.md', "<script>alert(1)</script>\n\n[x](javascript:alert%281%29)\n\n[[label:documentation.probe]]\n\n[[enum:DateTime::now]]\n");

            $guide = ModuleGuide::render($dir, 'en');

            $this->assertStringNotContainsString('<script', $guide->html);
            $this->assertStringNotContainsString('<img', $guide->html);
            $this->assertStringNotContainsString('javascript:', $guide->html);
            $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $guide->html);
            $this->assertSame(['DateTime::now'], $guide->missingKeys);
        } finally {
            @unlink($dir . '/en.md');
            @rmdir($dir);
        }
    }

    public function test_raw_html_in_a_guide_is_stripped(): void
    {
        $this->assertStringNotContainsString('<script>', ModuleGuide::render($this->fixture(), 'en')->html);
    }
}
