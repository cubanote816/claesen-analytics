<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Mailing\Mail\Transport\MicrosoftGraphTransport;
use Modules\Mailing\Services\MicrosoftGraphService;
use ReflectionClass;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * CLA-522 — certifica los subsistemas operativos (cache / queue / scheduler /
 * mail) bajo Laravel 13 + Symfony 8. El unico cambio de comportamiento real es
 * cache.serializable_classes (CLA-519), cuyo impacto se corrigio en
 * EmployeeDashboardRankingService (ver EmployeeRankingsTest). CLA-649 lo paso de
 * `false` a una allow-list de solo fechas: sigue sin admitir clases de aplicacion.
 */
class Laravel13CacheQueueMailCompatibilityTest extends TestCase
{
    /**
     * App cache payloads stay scalar/array. The only objects that may come back are dates
     * (CLA-649, for Filament's email MFA): whatever is on the list has to be a date type,
     * so no model, DTO or other application class can ever be rebuilt from the cache.
     */
    public function test_cache_serialization_is_locked_down(): void
    {
        $allowed = config('cache.serializable_classes');

        $this->assertIsArray($allowed);
        $this->assertNotEmpty($allowed);

        foreach ($allowed as $class) {
            $this->assertTrue(
                is_a($class, \DateTimeInterface::class, true) || $class === \DateTimeZone::class,
                "[{$class}] is not a date type and must not be unserializable from the cache.",
            );
        }
    }

    public function test_the_scheduler_has_no_duplicate_command_entries(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? $event->description)
            ->filter()
            ->map(fn ($command) => trim(str_replace(
                ["'".PHP_BINARY."'", PHP_BINARY, "'".base_path('artisan')."'", base_path('artisan'), 'artisan'],
                '',
                $command,
            )))
            ->values();

        $this->assertSame(
            $commands->unique()->values()->all(),
            $commands->all(),
            'schedule:list must contain a single entry per task.',
        );
    }

    public function test_microsoft_graph_transport_is_a_valid_symfony_8_transport(): void
    {
        // Mock, not app(...): MicrosoftGraphService::__construct reads Graph
        // credentials into typed string properties and TypeErrors when they are
        // unset (CI). This test only checks the Symfony transport contract, which
        // never touches the service.
        $transport = new MicrosoftGraphTransport($this->createMock(MicrosoftGraphService::class));

        $this->assertInstanceOf(AbstractTransport::class, $transport);

        // doSend(SentMessage) is the Symfony contract the transport implements.
        $doSend = (new ReflectionClass($transport))->getMethod('doSend');
        $this->assertSame(
            'Symfony\Component\Mailer\SentMessage',
            $doSend->getParameters()[0]->getType()?->getName(),
        );
    }
}
