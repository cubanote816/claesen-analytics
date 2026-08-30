<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Modules\Core\Http\Middleware\ResolveSessionCookieDomain;
use ReflectionClass;
use Tests\TestCase;

/**
 * CLA-521 — el unico cambio de Laravel 13 en la capa de auth/sesion/CSRF es
 * VerifyCsrfToken/ValidateCsrfToken -> PreventRequestForgery (aplicado en CLA-519).
 * PreventRequestForgery::handle() aprueba si:
 *   isReading || runningUnitTests || inExceptArray || hasValidOrigin || tokensMatch
 * hasValidOrigin() (Sec-Fetch-Site) es una via ADITIVA: solo lanza
 * OriginMismatchException si static::$originOnly esta activo, y solo aprueba
 * same-site sin token si static::$allowSameSite esta activo. Con ambos en su
 * default (false) el flujo de token clasico se preserva intacto.
 *
 * (El 419 end-to-end no se testea como feature: el guard `runningUnitTests()`
 * del middleware corta CSRF en el entorno de test por diseno. Se cubre por
 * revision de fuente en CLA-519 + la suite de auth de Modules/Core.)
 */
class Laravel13AuthCsrfCompatibilityTest extends TestCase
{
    public function test_sanctum_and_filament_route_csrf_through_prevent_request_forgery(): void
    {
        $this->assertSame(
            PreventRequestForgery::class,
            config('sanctum.middleware.validate_csrf_token'),
        );

        $this->assertContains(
            PreventRequestForgery::class,
            Filament::getPanel('admin')->getMiddleware(),
        );
    }

    public function test_prevent_request_forgery_keeps_its_backwards_compatible_defaults(): void
    {
        $reflection = new ReflectionClass(PreventRequestForgery::class);

        // $originOnly = false  => hasValidOrigin() never throws OriginMismatchException;
        //                         a missing Sec-Fetch-Site header falls through to the
        //                         classic token check instead of blocking.
        $this->assertFalse($this->staticValue($reflection, 'originOnly'));

        // $allowSameSite = false => a same-site request still needs a valid token;
        //                           only same-origin is auto-approved. Matches
        //                           Laravel 12's VerifyCsrfToken behaviour.
        $this->assertFalse($this->staticValue($reflection, 'allowSameSite'));

        // bootstrap/app.php does not call ->validateCsrfTokens(except: [...]),
        // so the only bypasses are the framework defaults.
        $this->assertSame([], $this->staticValue($reflection, 'neverVerify'));
    }

    public function test_session_cookie_domain_middleware_only_touches_the_session_domain(): void
    {
        // ResolveSessionCookieDomain runs before StartSession/CSRF and must only
        // mutate config('session.domain') — it has no CSRF concern.
        $source = file_get_contents(
            (new ReflectionClass(ResolveSessionCookieDomain::class))->getFileName(),
        );

        $this->assertSame(0, preg_match('/csrf/i', $source), 'ResolveSessionCookieDomain must not reference CSRF.');
        $this->assertStringContainsString('config([', $source);
        $this->assertStringContainsString("'session.domain'", $source);
    }

    private function staticValue(ReflectionClass $reflection, string $property): mixed
    {
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue();
    }
}
