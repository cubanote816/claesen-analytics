<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\Modules\Core\Http\Middleware\ResolveSessionCookieDomain::class);
        // The OAuth hint cookie is read by ResolveSessionCookieDomain, which is prepended
        // before EncryptCookies runs — so it must stay unencrypted to be readable there.
        $middleware->encryptCookies(except: [
            \Modules\Core\Http\Middleware\ResolveSessionCookieDomain::OAUTH_HINT_COOKIE,
        ]);
        $middleware->statefulApi();
        $middleware->alias([
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'organization' => \Modules\Core\Http\Middleware\RequireOrganization::class,
        ]);
        $middleware->web(append: [
            \Modules\Core\Http\Middleware\UpdateUserActivity::class,
            \Modules\Core\Http\Middleware\ResolveOrganizationContext::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        $schedule->command('app:sync-employees')->dailyAt('04:00');
        $schedule->command('website:process-reminders')->everyFifteenMinutes()->withoutOverlapping();
        // CLA-465: config('activitylog.clean_after_days') (365) existed since
        // CLA-526 but was never scheduled — retention was configured, never
        // enforced.
        $schedule->command('activitylog:clean')->dailyAt('04:30');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
