<?php

namespace Modules\Website\Services;

use Modules\Website\Contracts\MessageRepositoryInterface;
use Modules\Website\Contracts\ProjectRepositoryInterface;
use Modules\Website\DTOs\MessageData;
use Modules\Website\DTOs\ProjectData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class WebsiteService
{
    public function __construct(
        protected ProjectRepositoryInterface $projectRepository,
        protected MessageRepositoryInterface $messageRepository,
    ) {}

    /**
     * Get published portfolio projects as DTOs.
     */
    public function getPortfolio(int $perPage = 9): LengthAwarePaginator
    {
        $paginator = $this->projectRepository->getPublishedProjects($perPage);

        // Transform the collection within the paginator to DTOs
        $paginator->getCollection()->transform(function ($project) {
            return ProjectData::fromModel($project);
        });

        return $paginator;
    }

    /**
     * Get a single project by slug as DTO.
     */
    public function getProjectBySlug(string $slug): ProjectData
    {
        $project = $this->projectRepository->findBySlug($slug);

        if (!$project) {
            throw new ModelNotFoundException("Project not found: {$slug}");
        }

        return ProjectData::fromModel($project);
    }

    /**
     * Submit a contact message.
     */
    public function submitContactForm(MessageData $data): void
    {
        $this->messageRepository->store($data);
    }
}
