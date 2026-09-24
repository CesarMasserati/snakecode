<?php

declare(strict_types=1);

namespace SnakeCode\Domain\Level;

use SnakeCode\Domain\Coord;

/**
 * Obstáculo horizontal renderizado como uma região de código dobrada (fold).
 */
final readonly class Fold
{
    public function __construct(
        public int $y,
        public int $x,
        public int $length,
        public int $hiddenLines,
    ) {
    }

    public function contains(Coord $cell): bool
    {
        return $cell->y === $this->y && $cell->x >= $this->x && $cell->x < $this->x + $this->length;
    }

    public function within(int $cols, int $rows): bool
    {
        return $this->y < $rows && $this->x + $this->length <= $cols;
    }
}
