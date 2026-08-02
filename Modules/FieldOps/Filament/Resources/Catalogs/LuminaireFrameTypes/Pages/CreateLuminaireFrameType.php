<?php

namespace Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypes\Pages;

use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Locked;
use Modules\FieldOps\Filament\Resources\Catalogs\LuminaireFrameTypeResource;

class CreateLuminaireFrameType extends CreateRecord
{
    protected static string $resource = LuminaireFrameTypeResource::class;

    #[Locked]
    public ?string $returnTo = null;

    public function mount(): void
    {
        parent::mount();

        // Capture this during the initial page load. Livewire's create action
        // cannot reliably read the original query string through request().
        $this->returnTo = $this->normalizeReturnTo(request()->query('return_to'));
    }

    protected function getRedirectUrl(): string
    {
        if ($this->returnTo === null) {
            return parent::getRedirectUrl();
        }

        $parts = parse_url($this->returnTo);
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['new_frame_type_id'] = $this->record->getKey();

        return url($parts['path'] ?? '/').'?'.http_build_query($query);
    }

    private function normalizeReturnTo(mixed $returnTo): ?string
    {
        if (! is_string($returnTo) || $returnTo === '') {
            return null;
        }

        $parts = parse_url($returnTo);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if ($parts['scheme'] !== request()->getScheme() || $parts['host'] !== request()->getHost()) {
            return null;
        }

        $port = $parts['port'] ?? null;
        $requestPort = request()->getPort();

        if ($port !== null && $port !== $requestPort) {
            return null;
        }

        $path = $parts['path'] ?? '/';

        if (! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
