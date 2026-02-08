<?php

namespace Modules\Website\Services;

use Modules\Website\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class PortfolioService
{
    /**
     * Get filtering and paginated projects for the frontend.
     */
    public function getProjects(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        return QueryBuilder::for(Project::class)
            ->published()
            ->ordered()
            ->allowedFilters([
                AllowedFilter::exact('category'),
                AllowedFilter::exact('year'),
                AllowedFilter::scope('featured'),
            ])
            ->paginate($perPage)
            ->appends(request()->query());
    }

    /**
     * Get a single project by slug.
     */
    public function getProjectBySlug(string $slug): ?Project
    {
        return Project::where('slug', $slug)
            ->published()
            ->firstOrFail();
    }

    /**
     * Get detailed gallery for a project.
     * Assuming using Spatie MediaLibrary.
     */
    public function getProjectGallery(Project $project)
    {
        return $project->getMedia('gallery')->map(function ($media) {
            return [
                'url' => $media->getUrl(),
                'thumb' => $media->getUrl('thumb'), // Assuming thumb conversion exists
                'caption' => $media->getCustomProperty('caption'),
                'alt' => $media->getCustomProperty('alt'),
            ];
        });
    }

    /**
     * Get related projects based on category.
     */
    public function getRelatedProjects(Project $project, int $limit = 3): Collection
    {
        return Project::published()
            ->where('category', $project->category)
            ->where('id', '!=', $project->id)
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Get all unique categories from published projects.
     */
    public function getCategories(): array
    {
        return Project::published()
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Get all unique years from published projects.
     */
    public function getYears(): array
    {
        return Project::published()
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->filter()
            ->values()
            ->toArray();
    }
}
