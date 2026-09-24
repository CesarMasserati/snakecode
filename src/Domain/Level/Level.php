<?php

declare(strict_types=1);

namespace SnakeCode\Domain\Level;

/**
 * Parâmetros de um nível: velocidade, meta de comidas e quantidade de obstáculos (regiões dobradas).
 */
final readonly class Level
{
    public function __construct(
        public int $number,
        public int $tickMs,
        public int $foodsToNext,
        public int $folds,
    ) {
    }

    /**
     * Nome do branch exibido na statusbar.
     */
    public function branch(): string
    {
        return sprintf('feature/level-%d', $this->number);
    }
}
