<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Domain;

use PHPUnit\Framework\TestCase;
use SnakeCode\Domain\Level\LevelTable;

final class LevelTableTest extends TestCase
{
    public function testFixedLevels(): void
    {
        $table = new LevelTable();

        $first = $table->level(1);
        self::assertSame([180, 5, 0], [$first->tickMs, $first->foodsToNext, $first->folds]);

        $fourth = $table->level(4);
        self::assertSame([105, 8, 2], [$fourth->tickMs, $fourth->foodsToNext, $fourth->folds]);
    }

    public function testLateLevelsSpeedUpWithAFloor(): void
    {
        $table = new LevelTable();

        $fifth = $table->level(5);
        self::assertSame([97, 10, 3], [$fifth->tickMs, $fifth->foodsToNext, $fifth->folds]);
        self::assertSame(60, $table->level(100)->tickMs);
    }

    public function testInvalidNumbersFallBackToFirstLevel(): void
    {
        self::assertSame(1, (new LevelTable())->level(0)->number);
    }

    public function testBranchName(): void
    {
        self::assertSame('feature/level-3', (new LevelTable())->level(3)->branch());
    }
}
