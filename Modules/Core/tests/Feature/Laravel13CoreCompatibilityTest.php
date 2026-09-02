<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Modules\Website\Models\ConsultationRequest;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Tests\TestCase;

class Laravel13CoreCompatibilityTest extends TestCase
{
    public function test_the_application_uses_laravel_13(): void
    {
        $this->assertSame('13', explode('.', $this->app->version())[0]);
    }

    public function test_laravel_13_security_configuration_is_explicit(): void
    {
        $this->assertFalse(config('cache.serializable_classes'));
        $this->assertSame('php', config('session.serialization'));
    }

    public function test_sanctum_and_filament_use_the_request_forgery_middleware(): void
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

    public function test_the_activitylog_v5_bridge_is_loadable(): void
    {
        $request = new ConsultationRequest;

        $this->assertContains(LogsActivity::class, class_uses_recursive($request));
        $this->assertInstanceOf(LogOptions::class, $request->getActivitylogOptions());
    }
}
