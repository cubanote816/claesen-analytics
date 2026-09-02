<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Modules\Core\Http\Middleware\BrowserLocaleMiddleware;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Exercises BrowserLocaleMiddleware directly. The previous version hit `GET /`
 * and asserted App::getLocale() afterwards, but `/` is the Filament dashboard
 * (guest -> 302) so the middleware never ran and 3 of 4 assertions passed only
 * because they expected the default `nl`. It also predated the fr/de locales
 * being added to the supported list. CLA-524: made discoverable (#[Test]) and
 * rewritten against real middleware behaviour.
 */
class LocalizationTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function acceptLanguageProvider(): array
    {
        return [
            'english' => ['en-US', 'en'],
            'dutch' => ['nl-BE', 'nl'],
            'french is supported' => ['fr-FR', 'fr'],
            'german is supported' => ['de-DE', 'de'],
            'unsupported falls to nl' => ['es-ES', 'nl'],
            'missing header falls to en' => [null, 'en'],
        ];
    }

    #[Test]
    #[DataProvider('acceptLanguageProvider')]
    public function it_resolves_the_locale_from_the_accept_language_header(?string $acceptLanguage, string $expected): void
    {
        $server = $acceptLanguage !== null ? ['HTTP_ACCEPT_LANGUAGE' => $acceptLanguage] : [];
        $request = Request::create('/', 'GET', server: $server);
        $request->setLaravelSession($this->app['session']->driver());

        (new BrowserLocaleMiddleware)->handle($request, static fn () => new Response);

        $this->assertSame($expected, App::getLocale());
        $this->assertSame($expected, $request->session()->get('locale'));
    }
}
