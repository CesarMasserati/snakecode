<?php

declare(strict_types=1);

namespace SnakeCode\Domain;

/**
 * O que aconteceu em um passo da simulação.
 */
final readonly class StepResult
{
    public function __construct(
        public bool $turned,
        public bool $ate,
        public bool $levelUp,
    ) {
    }
}
