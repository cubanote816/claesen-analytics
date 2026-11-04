<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Models\User;
use Tests\TestCase;

/**
 * CLA-649: `cache.serializable_classes` decides which classes may come back out of
 * the cache. CLA-519 set it to `false` — no classes at all — on the premise that
 * "application cache entries use scalar or array payloads". Filament 5.10.3's email
 * MFA broke that premise: it caches `['hash' => string, 'expiresAt' => Carbon]`, and
 * with no class allowed the date returned as `__PHP_Incomplete_Class`. Its
 * `verifyCode()` requires `$expiresAt instanceof DateTimeInterface`, so every code —
 * including a correct one — was rejected.
 *
 * These tests run against the **database** store on purpose. `phpunit.xml` sets
 * `CACHE_STORE=array`, and the array store does not serialize at all, so it returns
 * any object intact and cannot observe this bug: the email MFA tests passed while
 * real sign-ins were impossible. A regression here has to read through the store dev
 * and production actually use.
 */
final class CacheSerializableClassesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store('database')->clear();
    }

    public function test_the_database_store_returns_dates_as_real_date_objects(): void
    {
        Cache::store('database')->put('cla649_date', now()->addMinutes(4), 120);

        $fromCache = Cache::store('database')->get('cla649_date');

        $this->assertInstanceOf(DateTimeInterface::class, $fromCache);
        $this->assertInstanceOf(Carbon::class, $fromCache);
    }

    public function test_an_immutable_date_also_survives_the_database_store(): void
    {
        Cache::store('database')->put('cla649_immutable', CarbonImmutable::now()->addMinutes(4), 120);

        $this->assertInstanceOf(
            DateTimeInterface::class,
            Cache::store('database')->get('cla649_immutable'),
        );
    }

    /**
     * The exact payload shape Filament's email MFA caches, and the exact check its
     * `verifyCode()` runs on the way out.
     */
    public function test_the_email_mfa_payload_survives_the_database_store(): void
    {
        $expiresAt = now()->addMinutes(4);

        Cache::store('database')->put('cla649_mfa', [
            'hash' => 'irrelevant-hash',
            'expiresAt' => $expiresAt,
        ], 120);

        $fromCache = Cache::store('database')->get('cla649_mfa');

        $this->assertIsString($fromCache['hash']);
        $this->assertInstanceOf(DateTimeInterface::class, $fromCache['expiresAt']);
        $this->assertSame($expiresAt->timestamp, $fromCache['expiresAt']->getTimestamp());
    }

    /**
     * The allow-list stays an allow-list: widening it for dates must not turn the
     * cache back into a general object-injection surface (the point of CLA-519).
     */
    public function test_arbitrary_application_classes_still_do_not_come_back_from_the_cache(): void
    {
        Cache::store('database')->put('cla649_model', new User(['email' => 'nobody@example.test']), 120);

        $fromCache = Cache::store('database')->get('cla649_model');

        $this->assertNotInstanceOf(User::class, $fromCache);
        $this->assertSame('__PHP_Incomplete_Class', get_class($fromCache));
    }
}
