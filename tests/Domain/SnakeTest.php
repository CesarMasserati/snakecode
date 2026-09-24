<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Domain;

use PHPUnit\Framework\TestCase;
use SnakeCode\Domain\Coord;
use SnakeCode\Domain\Direction;
use SnakeCode\Domain\Snake;

final class SnakeTest extends TestCase
{
    public function testSpawnLaysBodyBehindHead(): void
    {
        $snake = Snake::spawn(new Coord(5, 2), Direction::Right, 3);

        self::assertEquals([new Coord(5, 2), new Coord(4, 2), new Coord(3, 2)], $snake->segments());
    }

    public function testAdvanceWithoutGrowthKeepsLength(): void
    {
        $snake = Snake::spawn(new Coord(5, 2), Direction::Right, 3);
        $snake->advance(new Coord(6, 2), false);

        self::assertSame(3, $snake->length());
        self::assertEquals(new Coord(6, 2), $snake->head());
        self::assertFalse($snake->occupies(new Coord(3, 2)));
    }

    public function testAdvanceWithGrowthIncreasesLength(): void
    {
        $snake = Snake::spawn(new Coord(5, 2), Direction::Right, 3);
        $snake->advance(new Coord(6, 2), true);

        self::assertSame(4, $snake->length());
        self::assertTrue($snake->occupies(new Coord(3, 2)));
    }

    public function testTailCellCanBeIgnored(): void
    {
        $snake = Snake::spawn(new Coord(5, 2), Direction::Right, 3);

        self::assertTrue($snake->occupies($snake->tail()));
        self::assertFalse($snake->occupies($snake->tail(), ignoreTail: true));
    }

    public function testOppositeDirections(): void
    {
        self::assertSame(Direction::Down, Direction::Up->opposite());
        self::assertSame(Direction::Right, Direction::Left->opposite());
    }
}
