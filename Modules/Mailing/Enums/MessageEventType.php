<?php

namespace Modules\Mailing\Enums;

enum MessageEventType: string
{
    case SENT        = 'sent';
    case DELIVERED   = 'delivered';
    case OPENED      = 'opened';
    case CLICKED     = 'clicked';
    case BOUNCED_HARD  = 'bounced_hard';
    case BOUNCED_SOFT  = 'bounced_soft';
    case COMPLAINED  = 'complained';
    case UNSUBSCRIBED = 'unsubscribed';

    public function label(string $locale = 'en'): string
    {
        return match ($locale) {
            'nl' => match ($this) {
                self::SENT         => 'Verzonden',
                self::DELIVERED    => 'Afgeleverd',
                self::OPENED       => 'Geopend',
                self::CLICKED      => 'Geklikt',
                self::BOUNCED_HARD => 'Hard bounce',
                self::BOUNCED_SOFT => 'Soft bounce',
                self::COMPLAINED   => 'Spamklacht',
                self::UNSUBSCRIBED => 'Afgemeld',
            },
            default => match ($this) {
                self::SENT         => 'Sent',
                self::DELIVERED    => 'Delivered',
                self::OPENED       => 'Opened',
                self::CLICKED      => 'Clicked',
                self::BOUNCED_HARD => 'Hard bounce',
                self::BOUNCED_SOFT => 'Soft bounce',
                self::COMPLAINED   => 'Spam complaint',
                self::UNSUBSCRIBED => 'Unsubscribed',
            },
        };
    }
}
