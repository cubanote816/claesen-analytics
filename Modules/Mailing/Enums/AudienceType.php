<?php

namespace Modules\Mailing\Enums;

enum AudienceType: string
{
    case ALL_SUBSCRIBED = 'all_subscribed';
    case SEGMENT        = 'segment';
    case MANUAL         = 'manual';

    public function label(string $locale = 'en'): string
    {
        return match ($locale) {
            'nl' => match ($this) {
                self::ALL_SUBSCRIBED => 'Alle ingeschrevenen',
                self::SEGMENT        => 'Dynamisch segment',
                self::MANUAL         => 'Handmatige selectie',
            },
            default => match ($this) {
                self::ALL_SUBSCRIBED => 'All subscribed',
                self::SEGMENT        => 'Dynamic segment',
                self::MANUAL         => 'Manual selection',
            },
        };
    }
}
