<?php

namespace Modules\Website\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Intelligence\Services\GeminiService;
use Modules\Website\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateGalleryMediaMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const LOCALES = ['nl', 'en', 'fr', 'de'];

    public function __construct(private readonly int $mediaId) {}

    public function handle(GeminiService $gemini): void
    {
        $media = Media::find($this->mediaId);
        if (!$media) {
            return;
        }

        $project = $media->model;
        if (!$project instanceof Project) {
            return;
        }

        if ($this->isComplete($media)) {
            return;
        }

        $projectContext = [
            'title'       => $project->getTranslation('title', 'nl') ?: $project->getTranslation('title', 'en'),
            'category'    => $project->category instanceof \BackedEnum ? $project->category->value : (string) $project->category,
            'client'      => $project->getTranslation('client', 'nl') ?: $project->getTranslation('client', 'en'),
            'location'    => $project->getTranslation('location', 'nl') ?: $project->getTranslation('location', 'en'),
            'year'        => $project->year,
            'description' => $project->getTranslation('description', 'nl') ?: $project->getTranslation('description', 'en'),
        ];

        $userCaption = $media->getCustomProperty('caption_source') ?: null;
        $userAlt     = $media->getCustomProperty('alt_source') ?: null;

        try {
            $result = $gemini->generateMediaMetadata($projectContext, $userCaption, $userAlt, self::LOCALES);

            $media->setCustomProperty('caption', $result['caption']);
            $media->setCustomProperty('alt', $result['alt']);
            $media->saveQuietly();
        } catch (\Exception $e) {
            Log::error("GenerateGalleryMediaMetadataJob failed for media #{$this->mediaId}: " . $e->getMessage());
        }
    }

    private function isComplete(Media $media): bool
    {
        $caption = $media->getCustomProperty('caption', []);
        $alt     = $media->getCustomProperty('alt', []);

        foreach (self::LOCALES as $locale) {
            if (empty($caption[$locale] ?? '') || empty($alt[$locale] ?? '')) {
                return false;
            }
        }

        return true;
    }
}
