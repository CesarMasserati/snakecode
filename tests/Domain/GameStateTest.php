<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Domain;

use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use SnakeCode\Domain\CollisionException;
use SnakeCode\Domain\Coord;
use SnakeCode\Domain\Direction;
use SnakeCode\Domain\GameState;
use SnakeCode\Domain\Level\Fold;
use SnakeCode\Domain\Snake;

final class GameStateTest extends TestCase
{
    public function testInitialFoodIsNotOnTheSnake(): void
    {
        $state = $this->state();

        self::assertNotNull($state->food);
        self::assertFalse($state->snake->occupies($state->food));
    }

    public function testStepMovesForward(): void
    {
        $state = $this->state();
        $state->food = new Coord(0, 0);
        $head = $state->snake->head();

        $state->step();

        self::assertEquals(new Coord($head->x + 1, $head->y), $state->snake->head());
    }

    public function testReverseTurnIsIgnored(): void
    {
        $state = $this->state();
        $state->food = new Coord(0, 0);

        $result = $state->step(Direction::Left);

        self::assertFalse($result->turned);
        self::assertSame(Direction::Right, $state->direction);
    }

    public function testTurnIsReported(): void
    {
        $state = $this->state();
        $state->food = new Coord(0, 0);
        $head = $state->snake->head();

        $result = $state->step(Direction::Up);

        self::assertTrue($result->turned);
        self::assertSame(1, $state->turns);
        self::assertEquals(new Coord($head->x, $head->y - 1), $state->snake->head());
    }

    public function testBoundaryCollisionThrows(): void
    {
        $state = $this->state();
        $state->snake = Snake::spawn(new Coord(19, 5), Direction::Right, 3);
        $state->food = new Coord(0, 0);

        $this->expectException(CollisionException::class);
        $state->step();
    }

    public function testSelfCollisionThrows(): void
    {
        $state = $this->state();
        $state->snake = new Snake([new Coord(5, 5), new Coord(6, 5), new Coord(6, 6), new Coord(5, 6), new Coord(4, 6)]);
        $state->direction = Direction::Left;
        $state->food = new Coord(19, 0);

        $this->expectException(CollisionException::class);
        $state->step(Direction::Down);
    }

    public function testMovingIntoTheTailIsAllowed(): void
    {
        $state = $this->state();
        $state->snake = new Snake([new Coord(5, 5), new Coord(6, 5), new Coord(6, 6), new Coord(5, 6)]);
        $state->direction = Direction::Left;
        $state->food = new Coord(19, 0);

        $state->step(Direction::Down);

        self::assertEquals(new Coord(5, 6), $state->snake->head());
    }

    public function testEatingGrowsScoresAndRespawnsFood(): void
    {
        $state = $this->state();
        $state->food = $state->snake->head()->moved(Direction::Right);
        $length = $state->snake->length();

        $result = $state->step();

        self::assertTrue($result->ate);
        self::assertSame($length + 1, $state->snake->length());
        self::assertSame(10, $state->score);
        self::assertNotNull($state->food);
        self::assertFalse($state->snake->occupies($state->food));
    }

    public function testLevelUpAfterEnoughFood(): void
    {
        $state = $this->state();
        $state->eatenInLevel = $state->level->foodsToNext - 1;
        $state->food = $state->snake->head()->moved(Direction::Right);

        $result = $state->step();

        self::assertTrue($result->levelUp);
        self::assertSame(2, $state->level->number);
        self::assertSame(0, $state->eatenInLevel);
    }

    public function testFoldBlocksMovement(): void
    {
        $state = $this->state();
        $head = $state->snake->head();
        $state->folds = [new Fold($head->y, $head->x + 1, 5, 3)];
        $state->food = new Coord(0, 0);

        $this->expectException(CollisionException::class);
        $state->step();
    }

    public function testFoldsAreKeptAwayFromTheHead(): void
    {
        $state = $this->state(40, 16);
        for ($level = 1; $level < 3; $level++) {
            $state->eatenInLevel = $state->level->foodsToNext - 1;
            $state->food = $state->snake->head()->moved($state->direction);
            $state->step();
        }

        self::assertSame(3, $state->level->number);
        self::assertCount(1, $state->folds);
        foreach ($state->folds as $fold) {
            self::assertGreaterThan(2, abs($fold->y - $state->snake->head()->y));
            self::assertFalse($fold->contains($state->food));
        }
    }

    public function testResizeReportsWhenSnakeNoLongerFits(): void
    {
        $state = $this->state();

        self::assertTrue($state->resize(30, 12));
        self::assertFalse($state->resize(8, 4));
        self::assertTrue($state->resize(20, 10));
    }

    private function state(int $cols = 20, int $rows = 10): GameState
    {
        return new GameState($cols, $rows, new Randomizer(new Mt19937(7)));
    }
}
