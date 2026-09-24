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

    /** Nos níveis altos, cada passo é curto e os obstáculos aumentam até o 99. */
    private const MIN_TICK_MS = 32;

    public function level(int $number): Level
    {
        $number = max(1, min(100, $number));

        if (isset(self::TABLE[$number])) {
            [$tickMs, $foods, $folds] = self::TABLE[$number];

            return new Level($number, $tickMs, $foods, $folds);
        }

        $last = array_key_last(self::TABLE);
        [$tickMs, , $folds] = self::TABLE[$last];
        $extra = $number - $last;
        $tick = $tickMs - min(5, $extra) * 8 - max(0, $extra - 5) * 0.4;

        return new Level(
            $number,
            max(self::MIN_TICK_MS, (int) round($tick)),
            $number >= 20 ? 15 : 10,
            $number === 100 ? 100 : min(18, $folds + $extra),
        );
    }
}
