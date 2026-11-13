<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

/**
 * Something that PROPOSES group roles and spaces for one plan page.
 *
 * A provider never decides geometry: Kantoor rebuilds every polygon from the PDF's own
 * vectors. Callers must have checked the document's authorization for `id()` first.
 */
interface PlanAnalysisProvider
{
    public function id(): string;

    /**
     * @throws PlanAnalysisFailed
     */
    public function analyze(PlanAnalysisInput $input): PlanAnalysisResult;
}
