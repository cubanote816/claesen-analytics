<?php

namespace Modules\Mailing\Enums;

enum CampaignStatus: string
{
    case DRAFT     = 'draft';
    case REVIEW    = 'review';
    case APPROVED  = 'approved';
    case SENDING   = 'sending';
    case COMPLETED = 'completed';
    case FAILED    = 'failed';
    case CANCELLED = 'cancelled';

    /** States that can follow this one without authorization checks. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::DRAFT     => [self::REVIEW, self::CANCELLED],
            self::REVIEW    => [self::APPROVED, self::DRAFT, self::CANCELLED],
            self::APPROVED  => [self::SENDING, self::CANCELLED],
            self::SENDING   => [self::COMPLETED, self::FAILED],
            self::FAILED    => [self::DRAFT],
            self::COMPLETED,
            self::CANCELLED => [],
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::CANCELLED => true,
            default => false,
        };
    }

    public function label(string $locale = 'en'): string
    {
        return match ($locale) {
            'nl' => match ($this) {
                self::DRAFT     => 'Concept',
                self::REVIEW    => 'In beoordeling',
                self::APPROVED  => 'Goedgekeurd',
                self::SENDING   => 'Aan het verzenden',
                self::COMPLETED => 'Voltooid',
                self::FAILED    => 'Mislukt',
                self::CANCELLED => 'Geannuleerd',
            },
            default => match ($this) {
                self::DRAFT     => 'Draft',
                self::REVIEW    => 'Under review',
                self::APPROVED  => 'Approved',
                self::SENDING   => 'Sending',
                self::COMPLETED => 'Completed',
                self::FAILED    => 'Failed',
                self::CANCELLED => 'Cancelled',
            },
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT     => 'gray',
            self::REVIEW    => 'warning',
            self::APPROVED  => 'info',
            self::SENDING   => 'primary',
            self::COMPLETED => 'success',
            self::FAILED    => 'danger',
            self::CANCELLED => 'gray',
        };
    }
}
