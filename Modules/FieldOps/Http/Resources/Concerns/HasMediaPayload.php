<?php

namespace Modules\FieldOps\Http\Resources\Concerns;

trait HasMediaPayload
{
    // CLA-503 (2/2 — el frontend con el resolver defensivo ya está desplegado
    // primero): rutas relativas, no url(), que resuelve contra el Host interno
    // del túnel (backoffice.claesen.local) en vez del dominio público. Mismo
    // criterio que CLA-444 ya aplicó a LuminaireFrameType.image.
    protected function photosPayload(): \Illuminate\Support\Collection
    {
        return $this->getMedia('photos')->map(fn ($m) => [
            'id'        => $m->id,
            'name'      => $m->file_name,
            'url'       => "/api/v1/fieldops/media/{$m->id}",
            'thumb_url' => $m->hasGeneratedConversion('thumb')
                ? "/api/v1/fieldops/media/{$m->id}?conversion=thumb"
                : null,
        ]);
    }

    protected function documentsPayload(): \Illuminate\Support\Collection
    {
        return $this->getMedia('documents')->map(fn ($m) => [
            'id'   => $m->id,
            'name' => $m->file_name,
            'url'  => "/api/v1/fieldops/media/{$m->id}",
        ]);
    }

    protected function videosPayload(): \Illuminate\Support\Collection
    {
        return $this->getMedia('videos')->map(fn ($m) => [
            'id'        => $m->id,
            'name'      => $m->file_name,
            'mime_type' => $m->mime_type,
            'url'       => "/api/v1/fieldops/media/{$m->id}",
        ]);
    }
}
