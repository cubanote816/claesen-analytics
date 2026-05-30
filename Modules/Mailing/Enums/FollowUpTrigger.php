<?php

namespace Modules\Mailing\Enums;

enum FollowUpTrigger: string
{
    case CLICKED     = 'clicked';
    case NOT_CLICKED = 'not_clicked';
    case OPENED      = 'opened';
    case NOT_OPENED  = 'not_opened';

    /**
     * Returns true for triggers based on the open event.
     * Opens are a weak signal (Apple MPP, corporate proxies) — use with caution.
     */
    public function isOpenBased(): bool
    {
        return in_array($this, [self::OPENED, self::NOT_OPENED], true);
    }

    public function label(string $locale = 'en'): string
    {
        return match ($locale) {
            'nl' => match ($this) {
                self::CLICKED     => 'Heeft geklikt',
                self::NOT_CLICKED => 'Heeft NIET geklikt',
                self::OPENED      => 'Heeft geopend ⚠ zwak signaal',
                self::NOT_OPENED  => 'Heeft NIET geopend ⚠ zwak signaal',
            },
            default => match ($this) {
                self::CLICKED     => 'Clicked',
                self::NOT_CLICKED => 'Did NOT click',
                self::OPENED      => 'Opened ⚠ weak signal (Apple MPP)',
                self::NOT_OPENED  => 'Did NOT open ⚠ weak signal (Apple MPP)',
            },
        };
    }
}
