<?php

namespace Modules\Website\App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProjectCategory: string implements HasLabel
{
    case SPORT = 'sport';
    case INDUSTRIAL = 'industrial';
    case PUBLIC = 'public';

    public function getLabel(): ?string
    {
        return __("website.consultation_requests.categories.{$this->value}");
    }
}
