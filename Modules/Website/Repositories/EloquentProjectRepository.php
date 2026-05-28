<?php

namespace Modules\Website\Repositories;

use Modules\Website\Models\Project;
use Modules\Website\Contracts\ProjectRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentProjectRepository implements ProjectRepositoryInterface
{
    public function getPublishedProjects(int $perPage = 9): LengthAwarePaginator
    {
        return Project::where('published', true)
            ->orderBy('order_index')
            ->paginate($perPage);
    }

    public function findBySlug(string $slug): ?Project
    {
        return Project::where('slug', $slug)
            ->where('published', true)
            ->first();
    }
}
