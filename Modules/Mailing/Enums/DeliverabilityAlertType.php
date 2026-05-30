<?php

namespace Modules\Mailing\Enums;

enum DeliverabilityAlertType: string
{
    case HARD_BOUNCE_HIGH    = 'hard_bounce_high';
    case SPAM_COMPLAINT_HIGH = 'spam_complaint_high';

    public function label(string $locale = 'en'): string
    {
        return match ($locale) {
            'nl' => match ($this) {
                self::HARD_BOUNCE_HIGH    => 'Hard bounce rate te hoog',
                self::SPAM_COMPLAINT_HIGH => 'Spamklacht rate te hoog',
            },
            default => match ($this) {
                self::HARD_BOUNCE_HIGH    => 'Hard bounce rate too high',
                self::SPAM_COMPLAINT_HIGH => 'Spam complaint rate too high',
            },
        };
    }

    public function configKey(): string
    {
        return match ($this) {
            self::HARD_BOUNCE_HIGH    => 'mailing.hard_bounce_alert',
            self::SPAM_COMPLAINT_HIGH => 'mailing.spam_rate_alert',
        };
    }
}
