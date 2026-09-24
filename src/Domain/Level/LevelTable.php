<?php

declare(strict_types=1);

namespace SnakeCode\Domain\Level;

/**
 * Fonte da verdade da progressão de níveis.
 */
final class LevelTable
{
    /** Níveis fixos: número => [tick em ms, comidas para subir, regiões dobradas] */
    private const TABLE = [
        1 => [180, 5, 0],
        2 => [150, 6, 0],
        3 => [125, 7, 1],
        4 => [105, 8, 2],
    ];

    /** Curva rápida preservada no modo sem obstáculos. */
    private const MIN_TICK_MS = 32;
    /** Curva gradual para partidas com obstáculos. */
    private const OBSTACLE_START_TICK_MS = 190;
    private const LEVEL_100_WITH_OBSTACLES_MS = 125;

    public function level(int $number, bool $obstaclesEnabled = true): Level
    {
        $number = max(1, min(100, $number));

        if (isset(self::TABLE[$number])) {
            [$tickMs, $foods, $folds] = self::TABLE[$number];
            if ($obstaclesEnabled) {
                $tickMs = [1 => 220, 2 => 210, 3 => 200, 4 => 190][$number];
            }

            return new Level($number, $tickMs, $foods, $obstaclesEnabled ? $folds : 0);
        }

        $last = array_key_last(self::TABLE);
        [$tickMs, , $folds] = self::TABLE[$last];
        $extra = $number - $last;
        $tick = $tickMs - min(5, $extra) * 8 - max(0, $extra - 5) * 0.4;
        $obstacleTick = self::OBSTACLE_START_TICK_MS
            - (($number - $last) * (self::OBSTACLE_START_TICK_MS - self::LEVEL_100_WITH_OBSTACLES_MS) / (100 - $last));

        return new Level(
            $number,
            $obstaclesEnabled
                ? (int) round($obstacleTick)
                : max(self::MIN_TICK_MS, (int) round($tick)),
            5,
            !$obstaclesEnabled ? 0 : ($number === 100 ? 100 : min(18, $folds + $extra)),
        );
    }
}
