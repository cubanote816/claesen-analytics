<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Modules\Core\Http\Middleware\RequireOrganization;
use Modules\Core\Http\Middleware\ResolveOrganizationContext;
use Modules\Core\Http\Middleware\ResolveSessionCookieDomain;
use Modules\Core\Http\Middleware\UpdateUserActivity;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ResolveSessionCookieDomain::class);
        // The OAuth hint cookie is read by ResolveSessionCookieDomain, which is prepended
        // before EncryptCookies runs — so it must stay unencrypted to be readable there.
        $middleware->encryptCookies(except: [
            ResolveSessionCookieDomain::OAUTH_HINT_COOKIE,
        ]);
        $middleware->statefulApi();
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'organization' => RequireOrganization::class,
        ]);
        $middleware->web(append: [
            UpdateUserActivity::class,
            ResolveOrganizationContext::class,
        ]);
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('app:sync-employees')->dailyAt('04:00');
        $schedule->command('website:process-reminders')->everyFifteenMinutes()->withoutOverlapping();
        // CLA-465: config('activitylog.clean_after_days') (365) existed since
        // CLA-526 but was never scheduled — retention was configured, never
        // enforced.
        $schedule->command('activitylog:clean')->dailyAt('04:30');
        // F4/CLA-473: recovery for the observable-retries mechanism
        // Modules\Website\Jobs\SendConsultationEmailJob's own docblock
        // describes — a failed delivery is retried here, not via
        // Laravel's built-in backoff.
        $schedule->command('website:retry-failed-consultation-emails')->everyFifteenMinutes()->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // F3/CLA-471: the v1/website/* group is public, unauthenticated,
        // and unthrottled by design (see
        // tests/Feature/ClaesenBaseline/AccessControlContractTest) — the
        // one surface in this app where APP_DEBUG=true (the default in
        // .env/.env.example, confirmed leaking a full stack trace incl.
        // vendor file paths on a plain 404 before this fix) must never be
        // allowed to leak internals to an anonymous caller, regardless of
        // what the app-wide debug setting happens to be. Laravel's own
        // ValidationException rendering is already safe (field errors
        // only, no trace) and is left untouched — this only replaces
        // renders that would otherwise include debug-only keys.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('v1/website/*') || $e instanceof ValidationException) {
                return null;
            }

            $status = match (true) {
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            return response()->json([
                'message' => $status === 404 ? 'Not found.' : 'Something went wrong.',
            ], $status);
        });
    })->create();
