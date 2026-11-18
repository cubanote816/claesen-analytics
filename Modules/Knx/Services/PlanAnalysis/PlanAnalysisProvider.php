<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

/**
 * Something that PROPOSES group roles and spaces for one plan page.
 *
 * A provider never decides geometry: Kantoor rebuilds every polygon from the PDF's own
 * vectors.
 */
interface PlanAnalysisProvider
{
    public function id(): string;

    /**
     * `$maySend` answers, at that moment, whether the document is still authorized for
     * this provider. A provider that sends anything out of the server must ask it before
     * EVERY attempt (so a revocation also stops a retry) and fail with `not_authorized`
     * when it says no.
     *
     * @param  callable(): bool  $maySend
     *
     * @throws PlanAnalysisFailed
     */
    public function analyze(PlanAnalysisInput $input, callable $maySend): PlanAnalysisResult;
}
