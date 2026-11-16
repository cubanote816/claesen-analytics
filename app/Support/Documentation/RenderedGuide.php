<?php

declare(strict_types=1);

namespace App\Support\Documentation;

/** A user guide rendered in one locale (CLA-693). */
final readonly class RenderedGuide
{
    /**
     * @param  array<string, mixed>  $meta  YAML front matter
     * @param  list<array{id: string, text: string, level: int}>  $toc  h2/h3 headings with an anchor
     * @param  list<string>  $missingKeys  placeholder keys that have no translation
     */
    public function __construct(
        public string $locale,
        public string $title,
        public array $meta,
        public string $html,
        public array $toc,
        public array $missingKeys,
    ) {}

    /** @return list<string> every element id in the guide, in document order */
    public function anchors(): array
    {
        preg_match_all('/\bid="([^"]+)"/', $this->html, $matches);

        return array_map(
            fn (string $id): string => html_entity_decode($id, ENT_QUOTES | ENT_HTML5),
            $matches[1],
        );
    }
}
