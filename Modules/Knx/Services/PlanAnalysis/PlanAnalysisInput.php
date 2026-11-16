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
 * @phpstan-type Group array{id: string, kind: string, name: ?string, paths: int, strokeWidth: ?float, strokeColor: ?string, closedPct: int, curvedPct: int, ref?: int}
 * @phpstan-type Text array{id: string, text: string, x: float, y: float}
 * @phpstan-type Symbol array{id: string, group: string, x: float, y: float, w: float, h: float, paths: int, nearTextIds: list<string>, ref?: int}
 * @phpstan-type Legend array{x: float, y: float, w: float, h: float}
 */
final class PlanAnalysisInput
{
    /** Said to the model just before the reference image (S10). */
    public const REFERENCE_NOTE = 'Image 2: the same page dimmed, with labels S<ref> on symbol candidates and G<ref> on paths of each group; use it to locate them; answer with the canonical ids (groupId, symbolId) from the table.';

    /**
     * @param  list<Group>  $groups
     * @param  list<Text>  $texts
     * @param  list<Symbol>  $symbols  symbol candidates (plano IA v2); empty for a v1 request
     * @param  Legend|null  $legend  the detected legend region, or null
     * @param  string|null  $referencePng  the same page dimmed with S<ref>/G<ref> labels (S10), or null
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
        public readonly ?string $referencePng = null,
    ) {}

    /** The same page, tied to one processing for the USD ledger (ids only). */
    public function withSpend(AiSpendContext $spend): self
    {
        return new self($this->page, $this->pageWidthPt, $this->pageHeightPt, $this->imagePng, $this->groups, $this->texts, $spend, $this->symbols, $this->legend, $this->referencePng);
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
        $summary = [
            'page' => $this->page,
            'pageSizePt' => [$this->pageWidthPt, $this->pageHeightPt],
            'groups' => $this->groups,
            'texts' => $this->texts,
            'symbols' => $this->symbols,
            'legend' => $this->legend,
        ];
        if ($this->referencePng !== null) {
            $summary['refTable'] = $this->refTable();
        }

        return $summary;
    }

    /**
     * Label shown on the reference image => canonical id (`G<ref>` groups, `S<ref>` symbols).
     *
     * @return array{groups: array<string, string>, symbols: array<string, string>}
     */
    public function refTable(): array
    {
        $table = ['groups' => [], 'symbols' => []];
        foreach ($this->groups as $group) {
            if (isset($group['ref'])) {
                $table['groups']['G'.$group['ref']] = $group['id'];
            }
        }
        foreach ($this->symbols as $symbol) {
            if (isset($symbol['ref'])) {
                $table['symbols']['S'.$symbol['ref']] = $symbol['id'];
            }
        }

        return $table;
    }

    /**
     * The user message parts: summary, the page image and, when there is one, the note and the
     * reference image (always AFTER the page image). `$detail` is OpenAI's image detail, or null.
     *
     * @return list<array<string, string>>
     */
    public function contentParts(?string $detail): array
    {
        $image = fn (string $png): array => ['type' => 'input_image', 'image_url' => 'data:image/png;base64,'.base64_encode($png)]
            + ($detail === null ? [] : ['detail' => $detail]);

        $parts = [
            ['type' => 'input_text', 'text' => json_encode($this->summary(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
            $image($this->imagePng),
        ];
        if ($this->referencePng !== null) {
            $parts[] = ['type' => 'input_text', 'text' => self::REFERENCE_NOTE];
            $parts[] = $image($this->referencePng);
        }

        return $parts;
    }

    /**
     * @return array<string, string> text id => text
     */
    public function textsById(): array
    {
        return array_column($this->texts, 'text', 'id');
    }
}
