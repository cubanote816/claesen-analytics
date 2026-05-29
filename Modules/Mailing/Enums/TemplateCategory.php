<?php

namespace Modules\Mailing\Enums;

enum TemplateCategory: string
{
    case COMMERCIAL    = 'commercial';
    case TRANSACTIONAL = 'transactional';

    public function label(string $locale = 'en'): string
    {
        return match ($locale) {
            'nl' => match ($this) {
                self::COMMERCIAL    => 'Commercieel',
                self::TRANSACTIONAL => 'Transactioneel',
            },
            default => match ($this) {
                self::COMMERCIAL    => 'Commercial',
                self::TRANSACTIONAL => 'Transactional',
            },
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::COMMERCIAL    => 'info',
            self::TRANSACTIONAL => 'gray',
        };
    }
}
