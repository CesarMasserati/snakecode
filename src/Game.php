<?php

declare(strict_types=1);

namespace SnakeCode;

use Random\Engine\Mt19937;
use Random\Randomizer;
use SnakeCode\Domain\CollisionException;
use SnakeCode\Domain\Direction;
use SnakeCode\Domain\GameState;
use SnakeCode\Editor\SourceRepository;
use SnakeCode\Render\Renderer;
use SnakeCode\Render\Scene;
use SnakeCode\Render\Theme;
use SnakeCode\Storage\StateStore;
use SnakeCode\Terminal\Command;
use SnakeCode\Terminal\Input;
use SnakeCode\Terminal\Terminal;
use SnakeCode\Ui\MenuAction;
use SnakeCode\Ui\StartMenu;

/**
 * Laço principal: lê comandos, avança a simulação no ritmo do nível e redesenha a IDE.
 *
 * Cada curva da cobra abre um novo arquivo (aba) do repositório de código-fonte.
 */
final class Game
{
    private const MAX_QUEUED_TURNS = 2;
    private const IDLE_POLL_US = 250_000;
    private const TOAST_NS = 2_500_000_000;
    /** Células do terminal são ~2x mais altas que largas: o passo vertical é mais lento para compensar. */
    private const VERTICAL_FACTOR = 1.5;

    private readonly Randomizer $random;
    private GameState $state;
    private Mode $mode = Mode::Paused;
    private Mode $modeBeforePanic = Mode::Paused;
    /** @var list<Direction> */
    private array $turnQueue = [];
    private ?CollisionException $crash = null;
    private int $deaths = 0;
    private int $cols = 80;
    private int $rows = 24;
    private bool $tooSmall = false;
    private bool $quit = false;
    private ?string $toast = null;
    private int $toastUntil = 0;
    private string $title = '';
    private ?StartMenu $menu = null;
    /** A partida atual já deu ao menos um passo (define se o menu oferece "Continuar"). */
    private bool $started = false;

    public function __construct(
        private readonly Terminal $terminal,
        private readonly Input $input,
        private readonly Renderer $renderer,
        private readonly SourceRepository $sources,
        private readonly StateStore $store,
        private Theme $theme,
        private int $best,
        private readonly string $root,
        ?int $seed = null,
        bool $showMenu = true,
    ) {
        $this->random = $seed === null ? new Randomizer() : new Randomizer(new Mt19937($seed));
        if ($showMenu) {
            $this->menu = new StartMenu();
            $this->mode = Mode::Menu;
        }
    }

    public function run(): int
    {
        $this->terminal->enter();

        try {
            [$this->cols, $this->rows] = $this->terminal->size();
            $this->state = $this->newState();
            $this->handleResize();
            $this->notify(sprintf('Indexed %d files in %s', $this->sources->count(), $this->sources->workspaceName()));
            $this->loop();
        } finally {
            $this->terminal->restore();
            $this->persist();
        }

        return 0;
    }

    /**
     * Renderiza um único frame sem tocar no TTY (usado por --render-once).
     * Simula alguns passos com uma curva para exibir a troca de aba.
     */
    public function snapshot(int $cols, int $rows): string
    {
        $this->cols = $cols;
        $this->rows = $rows;
        $this->tooSmall = !Renderer::fits($cols, $rows);
        $this->state = $this->newState();
        if ($this->mode === Mode::Menu) {
            return $this->renderer->render($this->scene()) . "\n";
        }
        $this->mode = Mode::Running;

        foreach ([null, null, null, Direction::Down, null, null] as $turn) {
            try {
                $result = $this->state->step($turn);
            } catch (CollisionException $collision) {
                $this->crash = $collision;
                $this->mode = Mode::Crashed;
                break;
            }
            if ($result->turned) {
                $this->sources->next($this->boardRows());
            }
        }

        return $this->renderer->render($this->scene()) . "\n";
    }

    private function loop(): void
    {
        $nextTick = hrtime(true);
        $dirty = true;

        while (!$this->quit && !$this->terminal->interrupted()) {
            if ($this->terminal->consumeResize()) {
                $this->handleResize();
                $dirty = true;
            }
            if ($this->toast !== null && hrtime(true) >= $this->toastUntil) {
                $this->toast = null;
                $dirty = true;
            }
            if ($dirty) {
                $this->draw();
                $dirty = false;
            }

            $waitUs = $this->mode === Mode::Running
                ? max(0, intdiv($nextTick - hrtime(true), 1000))
                : self::IDLE_POLL_US;
            foreach ($this->input->poll($waitUs) as $command) {
                $this->handle($command);
                $dirty = true;
            }

            if ($this->mode !== Mode::Running) {
                $nextTick = hrtime(true) + $this->tickNs();
                continue;
            }

            $now = hrtime(true);
            if ($now >= $nextTick) {
                $this->tick();
                $dirty = true;
                $nextTick += $this->tickNs();
                if ($nextTick < $now) {
                    $nextTick = $now + $this->tickNs();
                }
            }
        }
    }

    private function tick(): void
    {
        $turn = array_shift($this->turnQueue);
        $previousLevel = $this->state->level;

        try {
            $result = $this->state->step($turn);
        } catch (CollisionException $collision) {
            $this->crashWith($collision);

            return;
        }

        $this->started = true;
        if ($result->turned) {
            $this->sources->next($this->boardRows());
        }
        if ($result->levelUp) {
            $this->notify(sprintf('✓ Merged %s into main', $previousLevel->branch()));
        }
    }

    private function handle(Command $command): void
    {
        if ($command === Command::Quit) {
            $this->quit = true;

            return;
        }
        if ($command === Command::Panic) {
            $this->togglePanic();

            return;
        }
        if ($this->mode === Mode::Panic) {
            return;
        }
        if ($this->mode === Mode::Menu) {
            $this->handleMenu($command);

            return;
        }

        match ($command) {
            Command::Up => $this->steer(Direction::Up),
            Command::Down => $this->steer(Direction::Down),
            Command::Left => $this->steer(Direction::Left),
            Command::Right => $this->steer(Direction::Right),
            Command::Pause => $this->togglePause(),
            Command::Stealth => $this->cycleTheme(1),
            Command::Confirm => $this->mode === Mode::Crashed ? $this->restart() : null,
            Command::Menu => $this->openMenu(),
            default => null,
        };
    }

    /**
     * No menu: ↑/↓ navegam, ←/→ ajustam a discrição, Enter/espaço confirmam.
     */
    private function handleMenu(Command $command): void
    {
        $menu = $this->menu;
        if ($menu === null) {
            $this->mode = Mode::Paused;

            return;
        }

        match ($command) {
            Command::Up => $menu->up(),
            Command::Down => $menu->down(),
            Command::Left => $menu->current() === MenuAction::Stealth ? $this->cycleTheme(-1) : null,
            Command::Right => $menu->current() === MenuAction::Stealth ? $this->cycleTheme(1) : null,
            Command::Stealth => $this->cycleTheme(1),
            Command::Confirm, Command::Pause => $this->activate($menu->current()),
            Command::Menu => $menu->canResume() ? $this->activate(MenuAction::Resume) : null,
            default => null,
        };
    }

    private function activate(MenuAction $action): void
    {
        match ($action) {
            MenuAction::Resume => $this->closeMenu(),
            MenuAction::NewGame => $this->startNewGame(),
            MenuAction::Stealth => $this->cycleTheme(1),
            MenuAction::Quit => $this->quit = true,
        };
    }

    /**
     * Abre o menu pausando o jogo. "Continuar" só aparece se a partida está em andamento.
     */
    private function openMenu(): void
    {
        $this->menu = new StartMenu(
            canResume: $this->started && $this->crash === null,
            hasPlayed: $this->started || $this->crash !== null,
        );
        $this->mode = Mode::Menu;
        $this->turnQueue = [];
    }

    private function closeMenu(): void
    {
        $this->menu = null;
        $this->mode = Mode::Paused;
    }

    private function startNewGame(): void
    {
        if ($this->started || $this->crash !== null) {
            $this->restart();
        }
        $this->closeMenu();
    }

    /**
     * Enfileira a curva validando contra a última direção pendente (evita meia-volta
     * quando duas teclas são pressionadas dentro do mesmo tick).
     */
    private function steer(Direction $direction): void
    {
        if ($this->mode === Mode::Crashed) {
            return;
        }
        if ($this->mode === Mode::Paused) {
            if ($this->tooSmall) {
                return;
            }
            $this->mode = Mode::Running;
        }

        $last = $this->turnQueue === [] ? $this->state->direction : $this->turnQueue[array_key_last($this->turnQueue)];
        if ($direction === $last || $direction === $last->opposite() || count($this->turnQueue) >= self::MAX_QUEUED_TURNS) {
            return;
        }
        $this->turnQueue[] = $direction;
    }

    private function togglePause(): void
    {
        if ($this->mode === Mode::Running) {
            $this->mode = Mode::Paused;
        } elseif ($this->mode === Mode::Paused && !$this->tooSmall) {
            $this->mode = Mode::Running;
        }
    }

    /**
     * Pânico: esconde tudo; ao voltar, o jogo fica pausado (nunca retoma em movimento).
     */
    private function togglePanic(): void
    {
        if ($this->mode === Mode::Panic) {
            $this->mode = $this->modeBeforePanic;

            return;
        }

        $this->modeBeforePanic = $this->mode === Mode::Running ? Mode::Paused : $this->mode;
        $this->mode = Mode::Panic;
        $this->turnQueue = [];
    }

    private function cycleTheme(int $step): void
    {
        $this->theme = $this->theme->shifted($step);
        $this->notify('Highlight: ' . $this->theme->profile);
    }

    private function crashWith(CollisionException $collision): void
    {
        $this->crash = $collision;
        $this->mode = Mode::Crashed;
        $this->turnQueue = [];
        $this->deaths++;
        $this->best = max($this->best, $this->state->score);
        $this->persist();
    }

    private function restart(): void
    {
        $this->state = $this->newState();
        $this->crash = null;
        $this->turnQueue = [];
        $this->started = false;
        $this->mode = Mode::Paused;
        $this->handleResize();
    }

    private function handleResize(): void
    {
        [$this->cols, $this->rows] = $this->terminal->size();
        $this->tooSmall = !Renderer::fits($this->cols, $this->rows);
        if (!$this->tooSmall) {
            [$boardCols, $boardRows] = Renderer::boardSize($this->cols, $this->rows);
            $this->tooSmall = !$this->state->resize($boardCols, $boardRows);
        }
        if ($this->tooSmall && $this->mode === Mode::Running) {
            $this->mode = Mode::Paused;
        }
    }

    private function newState(): GameState
    {
        [$boardCols, $boardRows] = Renderer::boardSize(
            max($this->cols, Renderer::MIN_COLS),
            max($this->rows, Renderer::MIN_ROWS),
        );

        return new GameState($boardCols, $boardRows, $this->random);
    }

    private function draw(): void
    {
        $file = $this->mode === Mode::Menu ? 'Welcome' : basename($this->sources->relativePath());
        $title = $file . ' — ' . $this->sources->workspaceName();
        if ($title !== $this->title) {
            $this->terminal->title($title);
            $this->title = $title;
        }

        $this->terminal->write($this->renderer->render($this->scene()));
    }

    private function scene(): Scene
    {
        return new Scene(
            cols: $this->cols,
            rows: $this->rows,
            state: $this->state,
            file: $this->sources->current(),
            path: $this->sources->relativePath(),
            startLine: $this->sources->startLine(),
            tabs: $this->sources->tabs(),
            theme: $this->theme,
            mode: $this->mode,
            crash: $this->crash,
            best: $this->best,
            deaths: $this->deaths,
            toast: $this->toast,
            tooSmall: $this->tooSmall,
            root: $this->root,
            menu: $this->menu,
            workspace: $this->sources->workspaceName(),
            fileCount: $this->sources->count(),
        );
    }

    private function notify(string $message): void
    {
        $this->toast = $message;
        $this->toastUntil = hrtime(true) + self::TOAST_NS;
    }

    private function tickNs(): int
    {
        $factor = $this->state->direction->isVertical() ? self::VERTICAL_FACTOR : 1.0;

        return (int) round($this->state->level->tickMs * 1_000_000 * $factor);
    }

    private function boardRows(): int
    {
        return Renderer::boardSize($this->cols, $this->rows)[1];
    }

    private function persist(): void
    {
        $best = $this->best;
        $profile = $this->theme->profile;

        $this->store->update(static fn (array $data): array => array_merge($data, [
            'best' => max($best, is_numeric($data['best'] ?? null) ? (int) $data['best'] : 0),
            'stealth' => $profile,
        ]));
    }
}
