<?php

namespace Modules\Prospects\Enums;

enum ContactMethod: string
{
    case Phone = 'phone';
    case Email = 'email';
    case InPerson = 'in_person';
    case WebsiteForm = 'website_form';
    case Other = 'other';

    public function label(): string
    {
        return __('prospects::resource.options.contact_methods.'.$this->value);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])->all();
    }
}
