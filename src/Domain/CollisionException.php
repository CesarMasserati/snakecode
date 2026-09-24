<?php

declare(strict_types=1);

namespace SnakeCode\Domain;

use RuntimeException;

/**
 * Lançada quando a cabeça atinge a borda do editor, o próprio corpo ou uma região dobrada.
 * O stack trace real desta exceção é o que aparece no painel TERMINAL no game over.
 */
final class CollisionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly Coord $at,
    ) {
        parent::__construct($message);
    }

    public static function boundary(Coord $at): self
    {
        return new self(sprintf('Undefined offset (%d, %d): viewport boundary exceeded', $at->x, $at->y), $at);
    }

    public static function circular(Coord $at): self
    {
        return new self(sprintf('Circular reference detected at Coord(%d, %d)', $at->x, $at->y), $at);
    }

    public static function folded(Coord $at): self
    {
        return new self(sprintf('Cannot write to folded region at Coord(%d, %d)', $at->x, $at->y), $at);
    }
}
