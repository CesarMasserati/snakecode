<?php

declare(strict_types=1);

namespace SnakeCode\Domain;

use InvalidArgumentException;

/**
 * Corpo da cobra: lista de segmentos com a cabeça no índice 0
 * e um mapa de ocupação para consultas de colisão em O(1).
 */
final class Snake
{
    /** @var list<Coord> */
    private array $body;

    /** @var array<string, int> quantidade de segmentos por célula */
    private array $occupancy = [];

    /**
     * @param list<Coord> $body
     */
    public function __construct(array $body)
    {
        if ($body === []) {
            throw new InvalidArgumentException('A cobra precisa de ao menos um segmento.');
        }

        $this->body = array_values($body);
        foreach ($this->body as $segment) {
            $this->occupancy[$segment->key()] = ($this->occupancy[$segment->key()] ?? 0) + 1;
        }
    }

    /**
     * Cria a cobra em linha reta, com o corpo atrás da cabeça.
     */
    public static function spawn(Coord $head, Direction $heading, int $length): self
    {
        $behind = $heading->opposite();
        $body = [$head];
        $segment = $head;
        for ($i = 1; $i < max(1, $length); $i++) {
            $segment = $segment->moved($behind);
            $body[] = $segment;
        }

        return new self($body);
    }

    public function head(): Coord
    {
        return $this->body[0];
    }

    public function tail(): Coord
    {
        return $this->body[array_key_last($this->body)];
    }

    public function length(): int
    {
        return count($this->body);
    }

    /**
     * @return list<Coord>
     */
    public function segments(): array
    {
        return $this->body;
    }

    /**
     * @param bool $ignoreTail a cauda libera a célula no mesmo passo em que a cabeça avança
     */
    public function occupies(Coord $cell, bool $ignoreTail = false): bool
    {
        $count = $this->occupancy[$cell->key()] ?? 0;
        if ($ignoreTail && $this->tail()->equals($cell)) {
            $count--;
        }

        return $count > 0;
    }

    public function advance(Coord $newHead, bool $grow): void
    {
        array_unshift($this->body, $newHead);
        $this->occupancy[$newHead->key()] = ($this->occupancy[$newHead->key()] ?? 0) + 1;

        if ($grow) {
            return;
        }

        $key = array_pop($this->body)->key();
        if (--$this->occupancy[$key] <= 0) {
            unset($this->occupancy[$key]);
        }
    }
}
