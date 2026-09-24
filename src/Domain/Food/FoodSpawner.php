<?php

declare(strict_types=1);

namespace SnakeCode\Domain\Food;

use Random\Randomizer;
use SnakeCode\Domain\Coord;

/**
 * Localiza uma célula livre para a próxima comida.
 */
final class FoodSpawner
{
    private const RANDOM_ATTEMPTS = 64;

    public function __construct(
        private readonly Randomizer $random,
    ) {
    }

    /**
     * @param callable(Coord): bool $isBlocked
     *
     * @return Coord|null null quando não há nenhuma célula livre
     */
    public function spawn(int $cols, int $rows, callable $isBlocked): ?Coord
    {
        for ($attempt = 0; $attempt < self::RANDOM_ATTEMPTS; $attempt++) {
            $cell = new Coord($this->random->getInt(0, $cols - 1), $this->random->getInt(0, $rows - 1));
            if (!$isBlocked($cell)) {
                return $cell;
            }
        }

        // Tabuleiro quase cheio: sorteia entre as células livres restantes.
        $free = [];
        for ($y = 0; $y < $rows; $y++) {
            for ($x = 0; $x < $cols; $x++) {
                $cell = new Coord($x, $y);
                if (!$isBlocked($cell)) {
                    $free[] = $cell;
                }
            }
        }

        return $free === [] ? null : $free[$this->random->getInt(0, count($free) - 1)];
    }
}
