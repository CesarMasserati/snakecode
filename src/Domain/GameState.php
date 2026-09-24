<?php

declare(strict_types=1);

namespace SnakeCode\Domain;

use InvalidArgumentException;
use Random\Randomizer;
use SnakeCode\Domain\Food\FoodSpawner;
use SnakeCode\Domain\Level\Fold;
use SnakeCode\Domain\Level\Level;
use SnakeCode\Domain\Level\LevelTable;

/**
 * Estado completo de uma partida e as regras de um passo da simulação.
 */
final class GameState
{
    private const INITIAL_LENGTH = 4;
    private const POINTS_PER_FOOD = 10;
    private const FOLD_MIN_LENGTH = 8;
    private const FOLD_MAX_LENGTH = 18;
    private const FOLD_ATTEMPTS = 200;
    /** Linhas acima/abaixo da cabeça onde nunca nasce um obstáculo. */
    private const SAFE_ROWS_AROUND_HEAD = 2;

    public Snake $snake;
    public Direction $direction = Direction::Right;
    public ?Coord $food = null;
    public Level $level;
    /** @var list<Fold> */
    public array $folds = [];
    public int $score = 0;
    public int $eatenInLevel = 0;
    public int $turns = 0;

    private int $cols;
    private int $rows;
    private readonly FoodSpawner $spawner;

    public function __construct(
        int $cols,
        int $rows,
        private readonly Randomizer $random,
        private readonly LevelTable $levels = new LevelTable(),
        int $startLevel = 1,
        private readonly bool $obstaclesEnabled = true,
    ) {
        if ($cols < 8 || $rows < 4) {
            throw new InvalidArgumentException(sprintf('Tabuleiro pequeno demais: %dx%d.', $cols, $rows));
        }

        $this->cols = $cols;
        $this->rows = $rows;
        $this->spawner = new FoodSpawner($random);
        $this->level = $levels->level($startLevel, $this->obstaclesEnabled);
        $this->snake = Snake::spawn(
            new Coord(intdiv($cols, 4) + self::INITIAL_LENGTH, intdiv($rows, 2)),
            $this->direction,
            self::INITIAL_LENGTH,
        );
        $this->placeFolds();
        $this->placeFood();
    }

    public function cols(): int
    {
        return $this->cols;
    }

    public function rows(): int
    {
        return $this->rows;
    }

    public function fillsBoard(): bool
    {
        return $this->snake->length() + $this->foldedCellCount() >= $this->cols * $this->rows;
    }

    public function foldedCellCount(): int
    {
        $cells = [];
        foreach ($this->folds as $fold) {
            for ($x = $fold->x; $x < $fold->x + $fold->length; $x++) {
                $cells[$fold->y . ':' . $x] = true;
            }
        }

        return count($cells);
    }

    /**
     * Avança um passo. Uma curva só é aceita se não for a direção atual nem a oposta.
     *
     * @throws CollisionException
     */
    public function step(?Direction $turn = null): StepResult
    {
        $turned = false;
        if ($turn !== null && $turn !== $this->direction && $turn !== $this->direction->opposite()) {
            $this->direction = $turn;
            $this->turns++;
            $turned = true;
        }

        $next = $this->snake->head()->moved($this->direction);

        if (!$next->within($this->cols, $this->rows)) {
            throw CollisionException::boundary($next);
        }
        if ($this->isFolded($next)) {
            throw CollisionException::folded($next);
        }

        $ate = $this->food !== null && $next->equals($this->food);
        if ($this->snake->occupies($next, ignoreTail: !$ate)) {
            throw CollisionException::circular($next);
        }

        $this->snake->advance($next, $ate);

        $levelUp = false;
        if ($ate) {
            $this->score += self::POINTS_PER_FOOD * $this->level->number;
            $this->eatenInLevel++;
            if ($this->eatenInLevel >= $this->level->foodsToNext && $this->level->number < 100) {
                $this->levelUp();
                $levelUp = true;
            } elseif ($this->level->number === 100) {
                $this->eatenInLevel = $this->level->foodsToNext;
            }
            $this->placeFood();
        }

        return new StepResult($turned, $ate, $levelUp);
    }

    /**
     * Ajusta o tabuleiro a um novo tamanho de viewport.
     *
     * @return bool false se a cobra ficou fora da área visível
     */
    public function resize(int $cols, int $rows): bool
    {
        $this->cols = $cols;
        $this->rows = $rows;
        $this->folds = array_values(array_filter(
            $this->folds,
            static fn (Fold $fold): bool => $fold->within($cols, $rows),
        ));

        foreach ($this->snake->segments() as $segment) {
            if (!$segment->within($cols, $rows)) {
                return false;
            }
        }

        if ($this->obstaclesEnabled && $this->level->number === 100 && count($this->folds) < $this->level->folds) {
            $this->placeFolds();
        }

        if ($this->food === null || !$this->food->within($cols, $rows)) {
            $this->placeFood();
        }

        return true;
    }

    public function isFolded(Coord $cell): bool
    {
        foreach ($this->folds as $fold) {
            if ($fold->contains($cell)) {
                return true;
            }
        }

        return false;
    }

    private function isBlocked(Coord $cell): bool
    {
        return $this->snake->occupies($cell) || $this->isFolded($cell);
    }

    private function levelUp(): void
    {
        if ($this->level->number >= 100) return;
        $this->level = $this->levels->level($this->level->number + 1, $this->obstaclesEnabled);
        $this->eatenInLevel = 0;
        $this->placeFolds();
    }

    private function placeFood(): void
    {
        $this->food = $this->spawner->spawn($this->cols, $this->rows, fn (Coord $cell): bool => $this->isBlocked($cell));
    }

    private function placeFolds(): void
    {
        $this->folds = [];
        if (!$this->obstaclesEnabled) return;
        $endgame = $this->level->number === 100;
        $maxLength = $endgame ? 1 : min(self::FOLD_MAX_LENGTH, intdiv($this->cols, 2));
        if ((!$endgame && $maxLength < self::FOLD_MIN_LENGTH) || (!$endgame && $this->rows < 5)) {
            return;
        }

        $attemptLimit = $endgame ? 5_000 : self::FOLD_ATTEMPTS;
        for ($attempt = 0; count($this->folds) < $this->level->folds && $attempt < $attemptLimit; $attempt++) {
            $length = $endgame ? 1 : $this->random->getInt(self::FOLD_MIN_LENGTH, $maxLength);
            $fold = new Fold(
                $this->random->getInt($endgame ? 0 : 1, $endgame ? $this->rows - 1 : $this->rows - 2),
                $this->random->getInt(0, $this->cols - $length),
                $length,
                $this->random->getInt(3, 42),
            );

            if ($this->canPlace($fold)) {
                $this->folds[] = $fold;
            }
        }

        if ($endgame && count($this->folds) < $this->level->folds) {
            for ($y = 0; $y < $this->rows && count($this->folds) < $this->level->folds; $y++) {
                for ($x = 0; $x < $this->cols && count($this->folds) < $this->level->folds; $x++) {
                    $fold = new Fold($y, $x, 1, 3);
                    if ($this->canPlace($fold)) $this->folds[] = $fold;
                }
            }
        }
    }

    private function canPlace(Fold $candidate): bool
    {
        if ($this->level->number !== 100 && abs($candidate->y - $this->snake->head()->y) <= self::SAFE_ROWS_AROUND_HEAD) {
            return false;
        }

        foreach ($this->folds as $fold) {
            if ($this->level->number !== 100 && abs($fold->y - $candidate->y) < 2) {
                return false;
            }
            if ($fold->y === $candidate->y
                && $candidate->x < $fold->x + $fold->length
                && $fold->x < $candidate->x + $candidate->length) {
                return false;
            }
        }

        for ($x = $candidate->x; $x < $candidate->x + $candidate->length; $x++) {
            $cell = new Coord($x, $candidate->y);
            if ($this->snake->occupies($cell) || ($this->food !== null && $this->food->equals($cell))) {
                return false;
            }
        }

        return true;
    }
}
