<?php

namespace Modules\Website\DTOs;

use Modules\Website\Models\Project;
use Illuminate\Support\Collection;

class ProjectData
{
    public function __construct(
        public int $id,
        public string $slug,
        public array $title,
        public ?array $content,
        public ?string $primary_image,
        public ?array $gallery,
        public ?array $seo_tags,
        public ?string $published_at,
    ) {}

    public static function fromModel(Project $project): self
    {
        return new self(
            id: $project->id,
            slug: $project->slug,
            title: $project->title,
            content: $project->content,
            primary_image: $project->primary_image ? asset('storage/' . $project->primary_image) : null,
            gallery: $project->api_gallery,
            seo_tags: $project->seo_tags,
            published_at: $project->published_at?->toIso8601String(),
        );
    }

    public static function collection(Collection $projects): Collection
    {
        return $projects->map(fn(Project $project) => self::fromModel($project));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'content' => $this->content,
            'primary_image' => $this->primary_image,
            'gallery' => $this->gallery,
            'seo_tags' => $this->seo_tags,
            'published_at' => $this->published_at,
        ];
    }
}
