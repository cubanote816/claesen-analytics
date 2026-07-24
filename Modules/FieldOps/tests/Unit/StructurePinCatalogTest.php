<?php

declare(strict_types=1);

namespace Modules\FieldOps\Tests\Unit;

use Modules\FieldOps\Support\StructurePinCatalog;
use Tests\TestCase;

class StructurePinCatalogTest extends TestCase
{
    public function test_definitions_expose_the_three_mast_codes(): void
    {
        self::assertSame(['conical', 'hinged', 'roof'], StructurePinCatalog::codes());
    }

    public function test_find_returns_the_matching_definition(): void
    {
        $pin = StructurePinCatalog::find('hinged');

        self::assertNotNull($pin);
        self::assertSame('hinged', $pin['code']);
        self::assertStringContainsString('<svg', $pin['svg']);
    }

    public function test_find_returns_null_for_an_unknown_or_missing_code(): void
    {
        self::assertNull(StructurePinCatalog::find('other'));
        self::assertNull(StructurePinCatalog::find(null));
    }

    public function test_fallback_svg_is_a_standalone_svg_document(): void
    {
        $svg = StructurePinCatalog::fallbackSvg();

        self::assertStringContainsString('<svg', $svg);
        self::assertStringContainsString('viewBox="0 0 160 230"', $svg);
    }
}
