<?php

declare(strict_types=1);

namespace Modules\Knx\Services\AiSpend;

use Illuminate\Support\Str;
use Modules\Knx\Support\KnxTenant;

/**
 * Who a call is for, as ids only: one plan processing of one document page.
 */
final class AiSpendContext
{
    public function __construct(
        public readonly int $organizationId,
        public readonly string $processingId,
        public readonly ?int $projectId = null,
        public readonly ?int $documentId = null,
        public readonly ?int $page = null,
        public readonly ?int $employeeId = null,
    ) {}

    /** A context without document (still tenant-bound, with its own processing id). */
    public static function detached(): self
    {
        return new self(KnxTenant::organizationId(), (string) Str::uuid());
    }
}
