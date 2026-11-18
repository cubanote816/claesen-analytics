<?php

declare(strict_types=1);

namespace Modules\Knx\Support;

/**
 * Topology check of a plan room (KNX-48): "no guardes topología inválida porque SVG la
 * dibuje". SVG paints a bow-tie or a hole sticking out of its polygon without complaint;
 * the server is the second barrier, after the client's own normalisation.
 *
 * A room is valid when its outer ring is simple (no self-intersection or touching, more
 * than a degenerate area, at most 2000 vertices), every hole is simple, every hole lies
 * strictly inside the outer ring without touching it, and holes are pairwise disjoint.
 * Rings are lists of `[x, y]`, open (the last point is not repeated). Touching counts as
 * invalid everywhere, so a repeated vertex is rejected too.
 */
final class PlanGeometry
{
    public const MAX_VERTICES = 2000;

    private const MIN_AREA = 1e-12;

    /**
     * @param  array<int, array<int, float|int>>  $outer
     * @param  array<int, array<int, array<int, float|int>>>  $holes
     * @return array{field: string, message: string}|null `field` is `polygon` or `holes`
     */
    public static function validate(array $outer, array $holes): ?array
    {
        if (($problem = self::ringProblem($outer)) !== null) {
            return ['field' => 'polygon', 'message' => 'The polygon '.$problem.'.'];
        }

        foreach ($holes as $i => $hole) {
            if (($problem = self::ringProblem($hole)) !== null) {
                return ['field' => 'holes', 'message' => 'Hole '.$i.' '.$problem.'.'];
            }

            foreach ($hole as $point) {
                if (self::locate($point, $outer) !== 1) {
                    return ['field' => 'holes', 'message' => 'Hole '.$i.' must lie strictly inside the polygon.'];
                }
            }

            if (self::ringsCross($hole, $outer)) {
                return ['field' => 'holes', 'message' => 'Hole '.$i.' must not touch or cross the polygon.'];
            }
        }

        $count = count($holes);
        $holes = array_values($holes);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                // Edges that neither cross nor touch leave one ring inside the other or
                // fully apart; one vertex tells which.
                if (self::ringsCross($holes[$i], $holes[$j])
                    || self::locate($holes[$i][0], $holes[$j]) !== -1
                    || self::locate($holes[$j][0], $holes[$i]) !== -1) {
                    return ['field' => 'holes', 'message' => 'Holes '.$i.' and '.$j.' must be disjoint.'];
                }
            }
        }

        return null;
    }

    /**
     * Why a ring is not a simple, non-degenerate ring, or null when it is.
     *
     * @param  array<int, array<int, float|int>>  $ring
     */
    private static function ringProblem(array $ring): ?string
    {
        $n = count($ring);

        if ($n < 3 || $n > self::MAX_VERTICES) {
            return 'must have between 3 and '.self::MAX_VERTICES.' vertices';
        }

        $ring = array_values($ring);

        $twice = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $n];
            if ($a[0] == $b[0] && $a[1] == $b[1]) {
                return 'repeats a vertex';
            }
            $twice += $a[0] * $b[1] - $b[0] * $a[1];
        }

        if (abs($twice) / 2 < self::MIN_AREA) {
            return 'has no area';
        }

        for ($i = 0; $i < $n; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $n];

            for ($j = $i + 1; $j < $n; $j++) {
                $c = $ring[$j];
                $d = $ring[($j + 1) % $n];

                $adjacent = $j === $i + 1 || ($i === 0 && $j === $n - 1);

                if ($adjacent) {
                    // Neighbours legitimately share one vertex; they are only wrong when
                    // the second one folds back over the first.
                    [$shared, $x, $y] = $j === $i + 1 ? [$b, $a, $d] : [$a, $b, $c];
                    $cross = ($shared[0] - $x[0]) * ($y[1] - $shared[1]) - ($shared[1] - $x[1]) * ($y[0] - $shared[0]);
                    $dot = ($shared[0] - $x[0]) * ($y[0] - $shared[0]) + ($shared[1] - $x[1]) * ($y[1] - $shared[1]);
                    if ($cross == 0 && $dot < 0) {
                        return 'is not simple';
                    }

                    continue;
                }

                if (self::segmentsMeet($a, $b, $c, $d)) {
                    return 'is not simple';
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<int, float|int>>  $first
     * @param  array<int, array<int, float|int>>  $second
     */
    private static function ringsCross(array $first, array $second): bool
    {
        $first = array_values($first);
        $second = array_values($second);
        $n = count($first);
        $m = count($second);

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $m; $j++) {
                if (self::segmentsMeet($first[$i], $first[($i + 1) % $n], $second[$j], $second[($j + 1) % $m])) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Closed segments share at least one point (touching and overlapping count). */
    private static function segmentsMeet(array $a, array $b, array $c, array $d): bool
    {
        if (max($a[0], $b[0]) < min($c[0], $d[0]) || max($c[0], $d[0]) < min($a[0], $b[0])
            || max($a[1], $b[1]) < min($c[1], $d[1]) || max($c[1], $d[1]) < min($a[1], $b[1])) {
            return false;
        }

        $o1 = self::orientation($a, $b, $c);
        $o2 = self::orientation($a, $b, $d);
        $o3 = self::orientation($c, $d, $a);
        $o4 = self::orientation($c, $d, $b);

        if ($o1 !== $o2 && $o3 !== $o4) {
            return true;
        }

        return ($o1 === 0 && self::within($a, $b, $c))
            || ($o2 === 0 && self::within($a, $b, $d))
            || ($o3 === 0 && self::within($c, $d, $a))
            || ($o4 === 0 && self::within($c, $d, $b));
    }

    private static function orientation(array $a, array $b, array $c): int
    {
        return ($b[0] - $a[0]) * ($c[1] - $a[1]) <=> ($b[1] - $a[1]) * ($c[0] - $a[0]);
    }

    /** `$p`, collinear with `$a`–`$b`, lies inside that segment's bounding box. */
    private static function within(array $a, array $b, array $p): bool
    {
        return $p[0] >= min($a[0], $b[0]) && $p[0] <= max($a[0], $b[0])
            && $p[1] >= min($a[1], $b[1]) && $p[1] <= max($a[1], $b[1]);
    }

    /**
     * 1 inside, 0 on the boundary, -1 outside.
     *
     * @param  array<int, float|int>  $p
     * @param  array<int, array<int, float|int>>  $ring
     */
    private static function locate(array $p, array $ring): int
    {
        $ring = array_values($ring);
        $n = count($ring);
        $inside = false;

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $a = $ring[$j];
            $b = $ring[$i];

            if (self::orientation($a, $b, $p) === 0 && self::within($a, $b, $p)) {
                return 0;
            }

            if (($b[1] > $p[1]) !== ($a[1] > $p[1])
                && $p[0] < ($a[0] - $b[0]) * ($p[1] - $b[1]) / ($a[1] - $b[1]) + $b[0]) {
                $inside = ! $inside;
            }
        }

        return $inside ? 1 : -1;
    }
}
