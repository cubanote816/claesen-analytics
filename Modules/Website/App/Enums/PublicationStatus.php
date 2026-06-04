<?php

namespace Modules\Website\App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PublicationStatus: string implements HasLabel, HasColor
{
    case IDLE     = 'idle';
    case PENDING  = 'pending';
    case ACCEPTED = 'accepted';
    case ERROR    = 'error';

    public function getLabel(): ?string
    {
        return __("website.publication.status.{$this->value}");
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::IDLE     => 'gray',
            self::PENDING  => 'warning',
            self::ACCEPTED => 'success',
            self::ERROR    => 'danger',
        };
    }
}
