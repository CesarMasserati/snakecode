<?php

declare(strict_types=1);

namespace SnakeCode\Ui;

use SnakeCode\Render\Theme;

/**
 * Estado do menu inicial (aba "Welcome"): opções disponíveis e seleção atual.
 */
final class StartMenu
{
    /** @var list<MenuAction> */
    private readonly array $items;
    private int $selected = 0;

    /**
     * @param bool $canResume há uma partida em andamento que pode ser retomada
     * @param bool $hasPlayed já houve partida nesta sessão ("Nova partida" em vez de "Iniciar")
     */
    public function __construct(
        private readonly bool $canResume = false,
        private readonly bool $hasPlayed = false,
    ) {
        $this->items = $canResume
            ? [MenuAction::Resume, MenuAction::NewGame, MenuAction::Stealth, MenuAction::Quit]
            : [MenuAction::NewGame, MenuAction::Stealth, MenuAction::Quit];
    }

    /**
     * @return list<MenuAction>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function selectedIndex(): int
    {
        return $this->selected;
    }

    public function current(): MenuAction
    {
        return $this->items[$this->selected];
    }

    public function canResume(): bool
    {
        return $this->canResume;
    }

    public function up(): void
    {
        $this->selected = ($this->selected - 1 + count($this->items)) % count($this->items);
    }

    public function down(): void
    {
        $this->selected = ($this->selected + 1) % count($this->items);
    }

    public function label(MenuAction $action, Theme $theme): string
    {
        return match ($action) {
            MenuAction::Resume => 'Continuar partida',
            MenuAction::NewGame => $this->hasPlayed ? 'Nova partida' : 'Iniciar',
            MenuAction::Stealth => sprintf('Discrição: ◀ %s ▶', $theme->profile),
            MenuAction::Quit => 'Sair',
        };
    }

    /**
     * Tecla que aciona a opção mesmo sem selecioná-la.
     */
    public function shortcut(MenuAction $action): string
    {
        return match ($action) {
            MenuAction::Resume => 'm',
            MenuAction::NewGame => '',
            MenuAction::Stealth => 'v',
            MenuAction::Quit => 'q',
        };
    }
}
