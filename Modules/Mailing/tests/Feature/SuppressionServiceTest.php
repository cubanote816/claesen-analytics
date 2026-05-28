<?php

namespace Modules\Mailing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Mailing\Enums\SuppressionReason;
use Modules\Mailing\Models\SuppressionEntry;
use Modules\Mailing\Services\SuppressionService;
use Tests\TestCase;

class SuppressionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SuppressionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SuppressionService();
    }

    public function test_is_suppressed_returns_false_for_unknown_email(): void
    {
        $this->assertFalse($this->service->isSuppressed('unknown@example.com'));
    }

    public function test_suppress_creates_suppression_entry(): void
    {
        $this->service->suppress('test@example.com', SuppressionReason::MANUAL);

        $this->assertDatabaseHas('mailing_suppression_list', [
            'email'  => 'test@example.com',
            'reason' => SuppressionReason::MANUAL->value,
        ]);
    }

    public function test_is_suppressed_returns_true_after_suppression(): void
    {
        $this->service->suppress('suppressed@example.com', SuppressionReason::HARD_BOUNCE);

        $this->assertTrue($this->service->isSuppressed('suppressed@example.com'));
    }

    public function test_get_reason_returns_null_for_unknown_email(): void
    {
        $this->assertNull($this->service->getReason('nobody@example.com'));
    }

    public function test_get_reason_returns_correct_reason(): void
    {
        $this->service->suppress('bounced@example.com', SuppressionReason::HARD_BOUNCE);

        $this->assertSame(SuppressionReason::HARD_BOUNCE, $this->service->getReason('bounced@example.com'));
    }

    public function test_suppress_normalizes_email_to_lowercase(): void
    {
        $this->service->suppress('Mixed.Case@Example.COM', SuppressionReason::MANUAL);

        $this->assertTrue($this->service->isSuppressed('mixed.case@example.com'));
    }

    public function test_suppress_permanent_reason_cannot_be_downgraded_to_non_permanent(): void
    {
        $this->service->suppress('victim@example.com', SuppressionReason::SPAM_COMPLAINT);

        $this->expectException(\DomainException::class);

        $this->service->suppress('victim@example.com', SuppressionReason::MANUAL);
    }

    public function test_suppress_can_upgrade_from_soft_bounce_to_hard_bounce(): void
    {
        $this->service->suppress('soft@example.com', SuppressionReason::SOFT_BOUNCE_LIMIT);
        $this->service->suppress('soft@example.com', SuppressionReason::HARD_BOUNCE);

        $this->assertSame(SuppressionReason::HARD_BOUNCE, $this->service->getReason('soft@example.com'));
    }

    public function test_suppress_is_idempotent_for_same_reason(): void
    {
        $this->service->suppress('dup@example.com', SuppressionReason::MANUAL);
        $this->service->suppress('dup@example.com', SuppressionReason::MANUAL);

        $this->assertSame(1, SuppressionEntry::where('email', 'dup@example.com')->count());
    }
}
