<?php

declare(strict_types=1);

namespace Modules\FieldOps\Support;

/** Canonical FieldOps map pin for electrical boards. */
class ElectricalBoardPinCatalog
{
    public static function svg(): string
    {
        return <<<'SVG'
<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
    <path d="M20 4C14.48 4 10 8.48 10 14C10 21.5 20 36 20 36C20 36 30 21.5 30 14C30 8.48 25.52 4 20 4Z" fill="#00aeef" stroke="white" stroke-width="1"/>
    <g transform="translate(20,14)">
        <rect x="-6.4" y="-8.2" width="12.8" height="16.4" rx="1.6" fill="none" stroke="white" stroke-width="1.3"/>
        <polygon points="0.5,-4.7 -4.2,0.9 -0.9,0.9 -1.4,4.7 4.2,-0.9 0.9,-0.9 1.4,-4.7" fill="white"/>
    </g>
</svg>
SVG;
    }
}
