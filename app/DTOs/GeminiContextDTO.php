<?php

namespace App\DTOs;

class GeminiContextDTO
{
    public string $locale;

    public function __construct(string $locale)
    {
        $this->locale = $locale;
    }

    public static function fromApp(): self
    {
        return new self(app()->getLocale());
    }
}
