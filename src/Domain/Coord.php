<?php

declare(strict_types=1);

namespace SnakeCode\Domain;

/**
 * Posição imutável no grid: x = coluna do código, y = linha visível do editor (ambos 0-based).
 */
final readonly class Coord
{
    public function __construct(
        public int $x,
        public int $y,
    ) {
    }

    public function moved(Direction $direction): self
    {
        [$dx, $dy] = $direction->delta();

        return new self($this->x + $dx, $this->y + $dy);
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    public function within(int $cols, int $rows): bool
    {
        return $this->x >= 0 && $this->y >= 0 && $this->x < $cols && $this->y < $rows;
    }

    /**
     * Chave estável para uso em mapas de ocupação.
     */
    public function key(): string
    {
        return $this->x . ':' . $this->y;
    }
}
