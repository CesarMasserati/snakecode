<?php

declare(strict_types=1);

namespace SnakeCode\Domain;

/**
 * Direção de deslocamento no grid do editor.
 */
enum Direction
{
    case Up;
    case Down;
    case Left;
    case Right;

    public function opposite(): self
    {
        return match ($this) {
            self::Up => self::Down,
            self::Down => self::Up,
            self::Left => self::Right,
            self::Right => self::Left,
        };
    }

    /**
     * @return array{0: int, 1: int} deslocamento [dx, dy]
     */
    public function delta(): array
    {
        return match ($this) {
            self::Up => [0, -1],
            self::Down => [0, 1],
            self::Left => [-1, 0],
            self::Right => [1, 0],
        };
    }

    public function isVertical(): bool
    {
        return $this === self::Up || $this === self::Down;
    }
}
