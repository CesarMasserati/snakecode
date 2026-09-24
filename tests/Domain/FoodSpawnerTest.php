<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use SnakeCode\Domain\Coord;
use SnakeCode\Domain\Food\FoodSpawner;

final class FoodSpawnerTest extends TestCase
{
    public function testNeverSpawnsOnBlockedCells(): void
    {
        $spawner = new FoodSpawner(new Randomizer(new Mt19937(1)));

        for ($i = 0; $i < 200; $i++) {
            $food = $spawner->spawn(10, 5, static fn (Coord $c): bool => $c->x < 7);
            self::assertNotNull($food);
            self::assertGreaterThanOrEqual(7, $food->x);
        }
    }

    public function testFindsTheLastFreeCell(): void
    {
        $spawner = new FoodSpawner(new Randomizer(new Mt19937(1)));

        $food = $spawner->spawn(4, 3, static fn (Coord $c): bool => !$c->equals(new Coord(2, 1)));

        self::assertEquals(new Coord(2, 1), $food);
    }

    public function testReturnsNullWhenBoardIsFull(): void
    {
        $spawner = new FoodSpawner(new Randomizer(new Mt19937(1)));

        self::assertNull($spawner->spawn(4, 3, static fn (): bool => true));
    }
}
