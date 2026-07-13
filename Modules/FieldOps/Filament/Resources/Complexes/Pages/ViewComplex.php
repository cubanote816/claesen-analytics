<?php

namespace Modules\FieldOps\Filament\Resources\Complexes\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Modules\FieldOps\Filament\Resources\ComplexResource;

class ViewComplex extends ViewRecord
{
    protected static string $resource = ComplexResource::class;
    protected Width|string|null $maxContentWidth = Width::Full;

    // See ViewFoClient::getTitle() for why this skips Filament's "View :label" wrapper.
    public function getTitle(): string|Htmlable
    {
        return $this->getRecordTitle();
    }

    // See ViewFoClient::getHeading() — profile-header.blade.php already shows the
    // eyebrow+name, so the native heading (which would repeat the same name) is hidden.
    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
