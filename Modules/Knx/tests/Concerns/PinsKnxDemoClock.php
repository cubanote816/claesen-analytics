<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Concerns;

use Carbon\CarbonImmutable;

/**
 * Pins the test clock before KnxDemoSeeder runs.
 *
 * The demo seed stamps fixtures "today at HH:MM" and moves a still-future hour one
 * day back (KnxDemoSeeder::at()), so anything that sorts by time depended on the
 * hour the suite ran: between 08:15 and 09:42 Europe/Brussels the conflict list and
 * the field inbox came back in another order (CI runs 40648706385, 40650339031).
 * Midday on a date with no DST change keeps every fixture hour in the past.
 *
 * Laravel calls setUp<TraitName>() from parent::setUp(), i.e. before the test's own
 * setUp() seeds.
 */
trait PinsKnxDemoClock
{
    protected function setUpPinsKnxDemoClock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-06-15 12:00:00', 'Europe/Brussels'));
    }
}
