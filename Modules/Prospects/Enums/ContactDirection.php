<?php

namespace Modules\Prospects\Enums;

enum ContactDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return __('prospects::resource.options.contact_directions.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Inbound => 'success',
            self::Outbound => 'info',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
