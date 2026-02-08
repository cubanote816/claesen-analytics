<?php

namespace Modules\Website\Contracts;

use Modules\Website\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProjectRepositoryInterface
{
    public function getPublishedProjects(int $perPage = 9): LengthAwarePaginator;
    public function findBySlug(string $slug): ?Project;
}
