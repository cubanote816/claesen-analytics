<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\Site;

/**
 * F3/CLA-468 of the multi-organization program — data only, per D7. Uses
 * DB::table(), never Eloquent (no model event side effects to trigger on a
 * pure catalog row anyway, but consistent with the program's convention).
 *
 * The three slugs and their translations are copied verbatim from the
 * hardcoded values this replaces: Modules\Website\App\Enums\ProjectCategory
 * (sport/industrial/public) and lang/{nl,en,fr,de}/website.php's
 * `projects.categories.*` keys — this migration changes where those values
 * live, not what they say.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        ['slug' => 'sport', 'order_index' => 0, 'name' => [
            'nl' => 'Sport', 'en' => 'Sport', 'fr' => 'Sport', 'de' => 'Sport',
        ]],
        ['slug' => 'industrial', 'order_index' => 1, 'name' => [
            'nl' => 'Industrieel', 'en' => 'Industrial', 'fr' => 'Industriel', 'de' => 'Industrie',
        ]],
        ['slug' => 'public', 'order_index' => 2, 'name' => [
            'nl' => 'Openbaar', 'en' => 'Public', 'fr' => 'Public', 'de' => 'Öffentlich',
        ]],
    ];

    public function up(): void
    {
        $siteId = Site::claesenId();
        $now = now();

        foreach (self::CATEGORIES as $category) {
            DB::table('website_project_categories')->updateOrInsert(
                ['site_id' => $siteId, 'slug' => $category['slug']],
                [
                    'name' => json_encode($category['name'], JSON_UNESCAPED_UNICODE),
                    'order_index' => $category['order_index'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    /**
     * Deliberate no-op (ADR "Rollback Plan"): the structure migration's
     * down() already drops the whole table, taking this seed data with it.
     * Nothing here ever deletes a row that a later, real content change
     * (an admin editing a category's name or adding a new one) might have
     * touched since this ran.
     */
    public function down(): void {}
};
