<?php

namespace Modules\Mailing\Enums;

enum SuppressionReason: string
{
    case UNSUBSCRIBED      = 'unsubscribed';
    case HARD_BOUNCE       = 'hard_bounce';
    case SPAM_COMPLAINT    = 'spam_complaint';
    case SOFT_BOUNCE_LIMIT = 'soft_bounce_limit';
    case MANUAL            = 'manual';

    public function isPermanent(): bool
    {
        return match ($this) {
            self::HARD_BOUNCE, self::SPAM_COMPLAINT => true,
            default => false,
        };
    }

    public function label(string $locale = 'en'): string
    {
        return match ($locale) {
            'nl' => match ($this) {
                self::UNSUBSCRIBED      => 'Afgemeld',
                self::HARD_BOUNCE       => 'Hard bounce',
                self::SPAM_COMPLAINT    => 'Spamklacht',
                self::SOFT_BOUNCE_LIMIT => 'Te veel soft bounces',
                self::MANUAL            => 'Manueel toegevoegd',
            },
            default => match ($this) {
                self::UNSUBSCRIBED      => 'Unsubscribed',
                self::HARD_BOUNCE       => 'Hard bounce',
                self::SPAM_COMPLAINT    => 'Spam complaint',
                self::SOFT_BOUNCE_LIMIT => 'Soft bounce limit reached',
                self::MANUAL            => 'Manually added',
            },
        };
    }
}
