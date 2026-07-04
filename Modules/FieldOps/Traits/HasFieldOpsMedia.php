<?php

namespace Modules\FieldOps\Traits;

trait HasFieldOpsMedia
{
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('local')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('documents')
            ->useDisk('local')
            ->acceptsMimeTypes(['application/pdf']);
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('photos')
            ->width(300)
            ->height(300)
            ->quality(85);
    }
}
