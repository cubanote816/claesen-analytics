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
use Modules\Website\Services\StaticSitePublicationService;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class GenerateGalleryMediaMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const LOCALES = ['nl', 'en', 'fr', 'de'];

    public function __construct(private readonly int $mediaId) {}

    public function handle(GeminiService $gemini): void
    {
        $notifyFrontend = false;

        try {
            $media = Media::find($this->mediaId);
            if (!$media) {
                return;
            }

            $project = $media->model;
            if (!$project instanceof Project) {
                return;
            }

            // Media confirmed as a Project gallery item — frontend must be notified regardless of outcome
            $notifyFrontend = true;

            $rawCaption = $media->getCustomProperty('caption');
            $rawAlt     = $media->getCustomProperty('alt');

            // Normalize: plain strings are legacy values — promote to source candidates
            $captionArray = is_array($rawCaption) ? $rawCaption : [];
            $altArray     = is_array($rawAlt)     ? $rawAlt     : [];

            if ($this->isComplete($captionArray, $altArray)) {
                return;
            }

            // Use explicit source fields first; fall back to legacy plain strings
            $userCaption = $media->getCustomProperty('caption_source') ?: null;
            $userAlt     = $media->getCustomProperty('alt_source') ?: null;

            if (!$userCaption && is_string($rawCaption) && !empty($rawCaption)) {
                $userCaption = $rawCaption;
            }
            if (!$userAlt && is_string($rawAlt) && !empty($rawAlt)) {
                $userAlt = $rawAlt;
            }

            $projectContext = [
                'title'       => $project->getTranslation('title', 'nl') ?: $project->getTranslation('title', 'en'),
                'category'    => $project->category instanceof \BackedEnum ? $project->category->value : (string) $project->category,
                'client'      => $project->getTranslation('client', 'nl') ?: $project->getTranslation('client', 'en'),
                'location'    => $project->getTranslation('location', 'nl') ?: $project->getTranslation('location', 'en'),
                'year'        => $project->year,
                'description' => $project->getTranslation('description', 'nl') ?: $project->getTranslation('description', 'en'),
            ];

            $result = $gemini->generateMediaMetadata($projectContext, $userCaption, $userAlt, self::LOCALES);

            $media->setCustomProperty('caption', $result['caption']);
            $media->setCustomProperty('alt', $result['alt']);
            $media->saveQuietly();

        } catch (\Exception $e) {
            Log::error("GenerateGalleryMediaMetadataJob failed for media #{$this->mediaId}: " . $e->getMessage());
        } finally {
            if ($notifyFrontend) {
                app(StaticSitePublicationService::class)->requestRebuild('content_changed');
            }
        }
    }

    private function isComplete(array $caption, array $alt): bool
    {
        foreach (self::LOCALES as $locale) {
            if (empty($caption[$locale] ?? '') || empty($alt[$locale] ?? '')) {
                return false;
            }
        }

        return true;
    }
}
