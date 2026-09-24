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

    /** A partir do último nível fixo: cada nível acelera 8 ms, até o piso de 60 ms. */
    private const STEP_MS = 8;
    private const MIN_TICK_MS = 60;
    private const LATE_FOODS_TO_NEXT = 10;

    public function level(int $number): Level
    {
        $number = max(1, $number);

        if (isset(self::TABLE[$number])) {
            [$tickMs, $foods, $folds] = self::TABLE[$number];

            return new Level($number, $tickMs, $foods, $folds);
        }

        $last = array_key_last(self::TABLE);
        [$tickMs, , $folds] = self::TABLE[$last];
        $extra = $number - $last;

        return new Level(
            $number,
            max(self::MIN_TICK_MS, $tickMs - self::STEP_MS * $extra),
            self::LATE_FOODS_TO_NEXT,
            $folds + $extra,
        );
    }
}
