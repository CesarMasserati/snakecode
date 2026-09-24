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
    ) {
        if ($cols < 8 || $rows < 4) {
            throw new InvalidArgumentException(sprintf('Tabuleiro pequeno demais: %dx%d.', $cols, $rows));
        }

        $this->cols = $cols;
        $this->rows = $rows;
        $this->spawner = new FoodSpawner($random);
        $this->level = $levels->level(1);
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
            if ($this->eatenInLevel >= $this->level->foodsToNext) {
                $this->levelUp();
                $levelUp = true;
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
        $this->level = $this->levels->level($this->level->number + 1);
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
        $maxLength = min(self::FOLD_MAX_LENGTH, intdiv($this->cols, 2));
        if ($maxLength < self::FOLD_MIN_LENGTH || $this->rows < 5) {
            return;
        }

        for ($attempt = 0; count($this->folds) < $this->level->folds && $attempt < self::FOLD_ATTEMPTS; $attempt++) {
            $length = $this->random->getInt(self::FOLD_MIN_LENGTH, $maxLength);
            $fold = new Fold(
                $this->random->getInt(1, $this->rows - 2),
                $this->random->getInt(0, $this->cols - $length),
                $length,
                $this->random->getInt(3, 42),
            );

            if ($this->canPlace($fold)) {
                $this->folds[] = $fold;
            }
        }
    }

    private function canPlace(Fold $candidate): bool
    {
        if (abs($candidate->y - $this->snake->head()->y) <= self::SAFE_ROWS_AROUND_HEAD) {
            return false;
        }

        foreach ($this->folds as $fold) {
            if (abs($fold->y - $candidate->y) < 2) {
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
