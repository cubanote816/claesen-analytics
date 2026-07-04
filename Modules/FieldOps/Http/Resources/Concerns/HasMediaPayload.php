<?php

namespace Modules\FieldOps\Http\Resources\Concerns;

trait HasMediaPayload
{
    protected function photosPayload(): \Illuminate\Support\Collection
    {
        return $this->getMedia('photos')->map(fn ($m) => [
            'id'        => $m->id,
            'name'      => $m->file_name,
            'url'       => url("/api/v1/fieldops/media/{$m->id}"),
            'thumb_url' => $m->hasGeneratedConversion('thumb')
                ? url("/api/v1/fieldops/media/{$m->id}?conversion=thumb")
                : null,
        ]);
    }

    protected function documentsPayload(): \Illuminate\Support\Collection
    {
        return $this->getMedia('documents')->map(fn ($m) => [
            'id'   => $m->id,
            'name' => $m->file_name,
            'url'  => url("/api/v1/fieldops/media/{$m->id}"),
        ]);
    }
}
