<?php

declare(strict_types=1);

namespace Tests\Feature\Documentation;

use App\Support\Documentation\ModuleGuide;
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
    }

    public function test_locales_without_a_guide_fall_back_to_english(): void
    {
        foreach (['fr', 'de', 'fr_BE'] as $locale) {
            $guide = ModuleGuide::render($this->fixture(), $locale);

            $this->assertSame('en', $guide->locale, $locale);
            $this->assertSame('Fixture guide', $guide->title, $locale);
        }
    }

    public function test_unknown_keys_are_reported_and_shown_as_missing(): void
    {
        $guide = ModuleGuide::render($this->fixture(), 'en');

        $this->assertSame(['mailing::resource.actions.does_not_exist'], $guide->missingKeys);
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

    public function test_raw_html_in_a_guide_is_stripped(): void
    {
        $this->assertStringNotContainsString('<script>', ModuleGuide::render($this->fixture(), 'en')->html);
    }
}
