<?php

declare(strict_types=1);

namespace Modules\Website\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\BelongsToSite;
use Modules\Website\Database\Factories\ProjectCategoryFactory;
use Spatie\Translatable\HasTranslations;

/**
 * F3/CLA-468 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * Replaces Modules\Website\App\Enums\ProjectCategory (a hardcoded, global
 * PHP enum every site would have shared) with a real, site-owned catalog —
 * see the create-table migration's docblock for the full rationale.
 *
 * Modules\Website\Models\Project::category stays a plain slug string (no
 * foreign key) — this model is the source of which slugs are valid for a
 * site, their display name and their order, consulted by the Filament form/
 * table filter, not a relation Project itself needs to load.
 */
class ProjectCategory extends Model
{
    use BelongsToSite, HasFactory, HasTranslations;

    protected $table = 'website_project_categories';

    protected $fillable = [
        'site_id',
        'slug',
        'name',
        'order_index',
    ];

    public $translatable = ['name'];

    protected static function newFactory(): ProjectCategoryFactory
    {
        return ProjectCategoryFactory::new();
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_index');
    }
}
