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
 * cache.serializable_classes => false (CLA-519), cuyo impacto se corrigio en
 * EmployeeDashboardRankingService (ver EmployeeRankingsTest).
 */
class Laravel13CacheQueueMailCompatibilityTest extends TestCase
{
    public function test_cache_serialization_is_locked_down(): void
    {
        // Laravel 13 skeleton default; app cache payloads must be scalar/array.
        $this->assertFalse(config('cache.serializable_classes'));
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
        $transport = new MicrosoftGraphTransport(app(MicrosoftGraphService::class));

        $this->assertInstanceOf(AbstractTransport::class, $transport);

        // doSend(SentMessage) is the Symfony contract the transport implements.
        $doSend = (new ReflectionClass($transport))->getMethod('doSend');
        $this->assertSame(
            'Symfony\Component\Mailer\SentMessage',
            $doSend->getParameters()[0]->getType()?->getName(),
        );
    }
}
