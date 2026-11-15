<?php

declare(strict_types=1);

namespace Modules\Knx\Services\PlanAnalysis;

use Modules\Knx\Services\AiSpend\AiSpendContext;

/**
 * What a provider may see of ONE plan page: the rendered page and the summary Kantoor
 * extracted from the PDF (stroke groups and texts, each with an opaque id).
 *
 * Coordinates are page fractions (0..1 from the top-left). The image is kept in memory
 * for the call only; it is never stored.
 *
 * @phpstan-type Group array{id: string, kind: string, name: ?string, paths: int, strokeWidth: ?float, strokeColor: ?string, closedPct: int, curvedPct: int}
 * @phpstan-type Text array{id: string, text: string, x: float, y: float}
 * @phpstan-type Symbol array{id: string, group: string, x: float, y: float, w: float, h: float, paths: int, nearTextIds: list<string>}
 * @phpstan-type Legend array{x: float, y: float, w: float, h: float}
 */
final class PlanAnalysisInput
{
    /**
     * @param  list<Group>  $groups
     * @param  list<Text>  $texts
     * @param  list<Symbol>  $symbols  symbol candidates (plano IA v2); empty for a v1 request
     * @param  Legend|null  $legend  the detected legend region, or null
     */
    public function __construct(
        public readonly int $page,
        public readonly float $pageWidthPt,
        public readonly float $pageHeightPt,
        public readonly string $imagePng,
        public readonly array $groups,
        public readonly array $texts,
        public readonly ?AiSpendContext $spend = null,
        public readonly array $symbols = [],
        public readonly ?array $legend = null,
    ) {}

    /** The same page, tied to one processing for the USD ledger (ids only). */
    public function withSpend(AiSpendContext $spend): self
    {
        return new self($this->page, $this->pageWidthPt, $this->pageHeightPt, $this->imagePng, $this->groups, $this->texts, $spend, $this->symbols, $this->legend);
    }

    /**
     * @return list<string>
     */
    public function groupIds(): array
    {
        return array_column($this->groups, 'id');
    }

    /**
     * @return list<string>
     */
    public function symbolIds(): array
    {
        return array_column($this->symbols, 'id');
    }

    /**
     * What a provider is shown of the page besides the image. All of it is DATA taken from
     * the PDF: a text that reads like an instruction is still only a text.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'page' => $this->page,
            'pageSizePt' => [$this->pageWidthPt, $this->pageHeightPt],
            'groups' => $this->groups,
            'texts' => $this->texts,
            'symbols' => $this->symbols,
            'legend' => $this->legend,
        ];
    }

    /**
     * @return array<string, string> text id => text
     */
    public function textsById(): array
    {
        return array_column($this->texts, 'text', 'id');
    }
}
