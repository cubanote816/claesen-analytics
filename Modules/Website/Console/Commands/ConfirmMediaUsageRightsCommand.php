<?php

declare(strict_types=1);

namespace Modules\Website\Console\Commands;

use Illuminate\Console\Command;
use Modules\Website\Models\Project;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * F3/CLA-467 of the multi-organization program — docs/ai/adr-multi-organization.md.
 *
 * "Registro de permiso de uso y revisión de privacidad" — records WHO
 * confirmed the organization holds usage rights for a given media item
 * (e.g. photographer/client consent for a portfolio photo) and, optionally
 * in the same run, WHO did the separate privacy review (e.g. confirming no
 * bystander/employee is identifiable without consent). Two distinct
 * timestamps because they are two distinct checks, not one box to tick.
 *
 * Same "Filament UI is a standalone feature, out of scope here" rationale
 * as SetMediaFocalPointCommand — this is a record-keeping action an
 * internal admin runs deliberately, not a per-upload form field.
 */
class ConfirmMediaUsageRightsCommand extends Command
{
    protected $signature = 'website:confirm-media-usage-rights
                            {media : Media ID}
                            {--by= : Identifier of the person confirming (e.g. an email)}
                            {--privacy-reviewed : Also record a privacy review by the same person, now}';

    protected $description = 'Record that usage rights (and optionally a privacy review) were confirmed for a Project media item';

    public function handle(): int
    {
        $by = $this->option('by');

        if (! $by) {
            $this->error('--by is required (identify who is confirming this).');

            return self::FAILURE;
        }

        $media = Media::find((int) $this->argument('media'));

        if (! $media || $media->model_type !== Project::class) {
            $this->error('No Project media found with that ID.');

            return self::FAILURE;
        }

        $now = now()->toIso8601String();

        $media->setCustomProperty('usage_rights_confirmed_at', $now);
        $media->setCustomProperty('usage_rights_confirmed_by', $by);

        if ($this->option('privacy-reviewed')) {
            $media->setCustomProperty('privacy_reviewed_at', $now);
            $media->setCustomProperty('privacy_reviewed_by', $by);
        }

        $media->save();

        $this->info("Usage rights confirmed for media #{$media->id} by {$by}.".
            ($this->option('privacy-reviewed') ? ' Privacy review recorded too.' : ''));

        return self::SUCCESS;
    }
}
