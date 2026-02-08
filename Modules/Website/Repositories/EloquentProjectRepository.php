<?php

namespace Modules\Website\Repositories;

use Modules\Website\Models\Project;
use Modules\Website\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function getPublishedProjects(int $perPage = 9): LengthAwarePaginator
    {
        return Project::where('is_published', true)
            ->where('published_at', '<=', now())
            ->orderBy('published_at', 'desc')
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): ?Project
    {
        return Project::where('slug', $slug)
            ->where('is_published', true)
            ->first();
    }
}
