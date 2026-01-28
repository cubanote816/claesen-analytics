<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\App;

class LocalizationTest extends TestCase
{
    /** @test */
    public function it_sets_locale_to_english_when_explicitly_requested()
    {
        $this->withHeaders(['Accept-Language' => 'en-US'])
            ->get('/');

        $this->assertEquals('en', App::getLocale());
    }

    /** @test */
    public function it_sets_locale_to_dutch_when_requested()
    {
        $this->withHeaders(['Accept-Language' => 'nl-BE'])
            ->get('/');

        $this->assertEquals('nl', App::getLocale());
    }

    /** @test */
    public function it_defaults_to_dutch_for_spanish()
    {
        $this->withHeaders(['Accept-Language' => 'es-ES'])
            ->get('/');

        $this->assertEquals('nl', App::getLocale());
    }

    /** @test */
    public function it_defaults_to_dutch_for_french()
    {
        $this->withHeaders(['Accept-Language' => 'fr-FR'])
            ->get('/');

        $this->assertEquals('nl', App::getLocale());
    }
}
