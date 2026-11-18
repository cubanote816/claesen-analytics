<?php

declare(strict_types=1);

namespace Modules\Knx\Tests\Unit;

use Modules\Knx\Support\PlanGeometry;
use PHPUnit\Framework\TestCase;

/**
 * The topology check of a plan room (KNX-48, S7): invalid shapes are rejected even though
 * SVG would paint them.
 */
final class PlanGeometryTest extends TestCase
{
    private const SQUARE = [[0.1, 0.1], [0.9, 0.1], [0.9, 0.9], [0.1, 0.9]];

    public function test_a_valid_l_shape_with_an_interior_hole_passes(): void
    {
        $l = [[0.1, 0.1], [0.9, 0.1], [0.9, 0.5], [0.5, 0.5], [0.5, 0.9], [0.1, 0.9]];
        $hole = [[0.2, 0.2], [0.3, 0.2], [0.3, 0.3], [0.2, 0.3]];

        $this->assertNull(PlanGeometry::validate($l, [$hole]));
        $this->assertNull(PlanGeometry::validate($l, []));
    }

    public function test_a_bow_tie_is_rejected_on_the_polygon(): void
    {
        $bowTie = [[0.1, 0.1], [0.9, 0.9], [0.9, 0.1], [0.1, 0.9]];

        $this->assertSame('polygon', PlanGeometry::validate($bowTie, [])['field']);
    }

    public function test_a_polygon_without_area_or_with_a_repeated_vertex_is_rejected(): void
    {
        $this->assertSame('polygon', PlanGeometry::validate([[0.1, 0.1], [0.5, 0.5], [0.9, 0.9]], [])['field']);
        $this->assertSame('polygon', PlanGeometry::validate([[0.1, 0.1], [0.1, 0.1], [0.9, 0.9], [0.1, 0.9]], [])['field']);
    }

    public function test_a_polygon_over_the_vertex_limit_is_rejected(): void
    {
        $ring = [];
        for ($i = 0; $i <= PlanGeometry::MAX_VERTICES; $i++) {
            $angle = 2 * M_PI * $i / (PlanGeometry::MAX_VERTICES + 1);
            $ring[] = [0.5 + 0.4 * cos($angle), 0.5 + 0.4 * sin($angle)];
        }

        $this->assertSame('polygon', PlanGeometry::validate($ring, [])['field']);
    }

    public function test_a_hole_outside_the_polygon_is_rejected(): void
    {
        $outside = [[0.95, 0.95], [0.99, 0.95], [0.99, 0.99]];

        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$outside])['field']);
    }

    public function test_a_hole_touching_the_outer_ring_is_rejected(): void
    {
        // Shares part of the left edge.
        $touching = [[0.1, 0.3], [0.3, 0.3], [0.3, 0.5], [0.1, 0.5]];
        // Touches the edge with a single vertex.
        $vertex = [[0.1, 0.5], [0.3, 0.4], [0.3, 0.6]];

        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$touching])['field']);
        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$vertex])['field']);
    }

    public function test_a_hole_crossing_the_outer_ring_is_rejected(): void
    {
        $crossing = [[0.5, 0.5], [1.0, 0.5], [1.0, 0.6], [0.5, 0.6]];
        $notch = [[0.05, 0.4], [0.3, 0.4], [0.3, 0.6], [0.05, 0.6]];

        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$crossing])['field']);
        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$notch])['field']);
    }

    public function test_overlapping_touching_and_nested_holes_are_rejected(): void
    {
        $a = [[0.2, 0.2], [0.5, 0.2], [0.5, 0.5], [0.2, 0.5]];
        $overlap = [[0.4, 0.4], [0.7, 0.4], [0.7, 0.7], [0.4, 0.7]];
        $touch = [[0.5, 0.2], [0.7, 0.2], [0.7, 0.5], [0.5, 0.5]];
        $nested = [[0.3, 0.3], [0.4, 0.3], [0.4, 0.4], [0.3, 0.4]];
        $apart = [[0.6, 0.6], [0.8, 0.6], [0.8, 0.8], [0.6, 0.8]];

        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$a, $overlap])['field']);
        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$a, $touch])['field']);
        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$a, $nested])['field']);
        $this->assertNull(PlanGeometry::validate(self::SQUARE, [$a, $apart]));
    }

    public function test_a_self_intersecting_hole_is_rejected(): void
    {
        $bowTie = [[0.2, 0.2], [0.4, 0.4], [0.4, 0.2], [0.2, 0.4]];

        $this->assertSame('holes', PlanGeometry::validate(self::SQUARE, [$bowTie])['field']);
    }
}
