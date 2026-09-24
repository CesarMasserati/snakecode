<?php

declare(strict_types=1);

namespace SnakeCode\Render;

use SnakeCode\Mode;

/**
 * Compõe o frame da "IDE": abas, breadcrumb, editor (tabuleiro) e statusbar.
 *
 * O tabuleiro é a própria área de código: a coluna X do jogo é a "Col X+1" do arquivo
 * e a linha Y é a linha (início do trecho + Y + 1) mostrada no gutter. Por isso a
 * statusbar "Ln/Col" e o breakpoint (●) no gutter apontam exatamente para a comida.
 */
final class Renderer
{
    public const GUTTER = 8;
    public const MIN_COLS = 60;
    public const MIN_ROWS = 16;

    /** Linhas fora do editor: abas, breadcrumb e statusbar. */
    private const CHROME_ROWS = 3;
    private const EDITOR_TOP = 2;
    private const PANEL_MAX_ROWS = 10;

    /** Abaixo desta largura o menu empilha as seções em uma coluna. */
    private const MENU_TWO_COLUMNS = 76;
    private const MENU_KEY_WIDTH = 16;
    private const MENU_SAMPLE_WIDTH = 13;

    /** Atalhos exibidos no menu: [teclas, descrição curta]. */
    private const SHORTCUTS = [
        ['←↑→↓ WASD hjkl', 'mover'],
        ['p / espaço', 'pausar'],
        ['v', 'discrição'],
        ['Esc / `', 'PÂNICO: só código'],
        ['m', 'abrir este menu'],
        ['Enter', 'reiniciar'],
        ['q / Ctrl+C', 'sair'],
    ];

    /**
     * @return array{0: int, 1: int} colunas e linhas do tabuleiro para um terminal cols×rows
     */
    public static function boardSize(int $cols, int $rows): array
    {
        return [max(1, $cols - self::GUTTER), max(1, $rows - self::CHROME_ROWS)];
    }

    public static function fits(int $cols, int $rows): bool
    {
        return $cols >= self::MIN_COLS && $rows >= self::MIN_ROWS;
    }

    public function render(Scene $scene): string
    {
        return $this->canvas($scene)->toAnsi();
    }

    public function canvas(Scene $scene): Canvas
    {
        $canvas = new Canvas($scene->cols, $scene->rows, Theme::FG, Theme::BG);

        if ($scene->tooSmall) {
            $this->drawTooSmall($canvas);

            return $canvas;
        }

        $this->drawTabs($canvas, $scene);
        $this->drawBreadcrumb($canvas, $scene);
        if ($scene->mode === Mode::Menu) {
            $this->drawMenu($canvas, $scene);
        } elseif ($scene->mode === Mode::Settings || $scene->mode === Mode::SettingsPath) {
            $this->drawSettings($canvas, $scene);
        } else {
            $this->drawEditor($canvas, $scene);
        }
        if ($scene->mode === Mode::Crashed && $scene->crash !== null) {
            $this->drawPanel($canvas, $scene);
        }
        if ($scene->mode === Mode::Won) $this->drawWon($canvas);
        $this->drawStatus($canvas, $scene);

        return $canvas;
    }

    private function drawTooSmall(Canvas $canvas): void
    {
        $lines = [
            'Window too small for the current session',
            sprintf('Resize to at least %d×%d (current %d×%d)', self::MIN_COLS, self::MIN_ROWS, $canvas->width, $canvas->height),
        ];
        $top = max(0, intdiv($canvas->height - count($lines), 2));
        foreach ($lines as $i => $line) {
            $canvas->text(max(0, intdiv($canvas->width - mb_strlen($line), 2)), $top + $i, $line, Theme::BREADCRUMB_FG);
        }
    }

    private function drawTabs(Canvas $canvas, Scene $scene): void
    {
        $canvas->fillRow(0, Theme::TAB_BAR_BG);
        $tabs = $scene->mode === Mode::Menu ? ['Welcome', ...$scene->tabs] : $scene->tabs;
        $x = 0;
        foreach ($tabs as $i => $path) {
            $active = $i === 0;
            $x = $canvas->text(
                $x,
                0,
                ' ' . basename($path) . ($active ? ' × ' : '   '),
                $active ? Theme::TAB_ACTIVE_FG : Theme::TAB_FG,
                $active ? Theme::BG : Theme::TAB_BAR_BG,
                $active ? 0 : Canvas::ITALIC,
            );
            $x = $canvas->text($x, 0, '│', Theme::TAB_BORDER, Theme::TAB_BAR_BG);
        }
    }

    private function drawBreadcrumb(Canvas $canvas, Scene $scene): void
    {
        if ($scene->mode === Mode::Menu) {
            $canvas->text(0, 1, ' Welcome', Theme::BREADCRUMB_FG, Theme::BG);

            return;
        }

        // Como numa IDE, o breadcrumb acompanha o cursor (a cabeça da cobra).
        $cursorRow = $scene->mode === Mode::Panic && $scene->editor !== null
            ? $scene->editor->row() - $scene->startLine
            : ($scene->showsGame() ? $scene->state->snake->head()->y : 0);
        $symbol = $scene->file->symbolAt($scene->startLine + $cursorRow + 1);
        $text = ' ' . implode(' › ', explode('/', $scene->path)) . ($symbol !== null ? ' › ' . $symbol : '');

        $canvas->text(0, 1, $text, Theme::BREADCRUMB_FG, Theme::BG);
    }

    private function drawEditor(Canvas $canvas, Scene $scene): void
    {
        [$boardCols, $boardRows] = self::boardSize($scene->cols, $scene->rows);
        $state = $scene->state;
        $showGame = $scene->showsGame();
        $head = $state->snake->head();
        $food = $state->food;

        for ($y = 0; $y < $boardRows; $y++) {
            $screenY = self::EDITOR_TOP + $y;
            $lineIndex = $scene->startLine + $y;
            $isCursorRow = $showGame && $head->y === $y;
            $isFoodRow = $showGame && $food !== null && $food->y === $y;
            $rowBg = $isCursorRow ? Theme::LINE_HIGHLIGHT : Theme::BG;

            $canvas->fillRow($screenY, $rowBg);
            $canvas->text(0, $screenY, sprintf('%5d', $lineIndex + 1), $isCursorRow ? Theme::GUTTER_ACTIVE_FG : Theme::GUTTER_FG, $rowBg);
            if ($isFoodRow) {
                $canvas->text(6, $screenY, '●', Theme::BREAKPOINT, $rowBg);
            }

            [$chars, $colors] = $scene->file->lines[$lineIndex] ?? [[], []];
            $visible = min(max(0, count($chars) - $scene->startColumn), $boardCols);
            for ($x = 0; $x < $visible; $x++) {
                $canvas->put(self::GUTTER + $x, $screenY, $chars[$x + $scene->startColumn], $colors[$x + $scene->startColumn]);
            }

            if ($isFoodRow) {
                // Mensagem inline no estilo "Error Lens", sempre à direita da comida.
                $hintX = max(count($chars), $food->x + 1) + 2;
                $canvas->text(
                    self::GUTTER + $hintX,
                    $screenY,
                    sprintf('■ Undefined variable $food at col %d', $food->x + 1),
                    Theme::HINT_FG,
                    null,
                    Canvas::ITALIC,
                );
            }
        }

        if (!$showGame) {
            if ($scene->mode === Mode::Panic && $scene->editor !== null) {
                $editor = $scene->editor;
                $cursorY = $editor->row() - $scene->startLine;
                if ($cursorY >= 0 && $cursorY < $boardRows) {
                    $x = self::GUTTER + min(max(0, $editor->column() - $scene->startColumn), $boardCols - 1);
                    $screenY = self::EDITOR_TOP + $cursorY;
                    [$chars, $colors] = $scene->file->lines[$editor->row()] ?? [[], []];
                    $index = $editor->column();
                    $character = $chars[$index] ?? ' ';
                    $color = $colors[$index] ?? Theme::FG;
                    $canvas->put($x, $screenY, $character, Theme::BG, $color, Canvas::BOLD);
                }
            }
            return;
        }

        foreach ($state->folds as $fold) {
            $screenY = self::EDITOR_TOP + $fold->y;
            for ($x = $fold->x; $x < $fold->x + $fold->length; $x++) {
                $canvas->put(self::GUTTER + $x, $screenY, ' ', Theme::FOLD_FG, Theme::FOLD_BG, 0);
            }
            $label = mb_substr(sprintf('▸ ⋯ %d lines', $fold->hiddenLines), 0, $fold->length);
            $canvas->text(self::GUTTER + $fold->x, $screenY, $label, Theme::FOLD_FG, Theme::FOLD_BG);
        }

        $theme = $scene->theme;
        if ($food !== null) {
            $canvas->put(
                self::GUTTER + $food->x,
                self::EDITOR_TOP + $food->y,
                $theme->foodChar(),
                $theme->foodFg(),
                null,
                Canvas::CURLY | Canvas::UNDERLINE | ($theme->foodFg() !== null ? Canvas::BOLD : 0),
            );
        }

        foreach ($state->snake->segments() as $i => $segment) {
            $canvas->put(
                self::GUTTER + $segment->x,
                self::EDITOR_TOP + $segment->y,
                null,
                $i === 0 ? $theme->headFg() : $theme->bodyFg(),
                $i === 0 ? $theme->headBg() : $theme->bodyBg(),
                0,
            );
        }
    }

    /**
     * Aba "Welcome": opções à esquerda (com atalhos) e legenda "Como funciona" à direita.
     * As amostras da legenda usam o perfil de discrição atual (pré-visualização ao vivo).
     */
    private function drawMenu(Canvas $canvas, Scene $scene): void
    {
        [, $boardRows] = self::boardSize($scene->cols, $scene->rows);
        $bottom = self::EDITOR_TOP + $boardRows;
        for ($y = self::EDITOR_TOP; $y < $bottom; $y++) {
            $canvas->fillRow($y, Theme::BG);
        }

        $menu = $scene->menu;
        if ($menu === null) {
            return;
        }

        // Escreve só dentro do editor (nunca sobre a statusbar) e dentro da largura da coluna.
        $write = static fn (int $x, int $y, string $text, int $fg, int $bg = Theme::BG, int $fx = 0, int $max = PHP_INT_MAX): int
            => $y < $bottom ? $canvas->text($x, $y, mb_substr($text, 0, max(0, $max)), $fg, $bg, $fx) : $x;

        $twoColumns = $scene->cols >= self::MENU_TWO_COLUMNS;
        $left = $scene->cols >= 100 ? 4 : 2;
        $columnWidth = $twoColumns ? min(40, intdiv($scene->cols - $left, 2) - 2) : $scene->cols - 2 * $left;
        $y = self::EDITOR_TOP + 1;

        $write($left, $y++, 'SnakeCode', Theme::TAB_ACTIVE_FG, Theme::BG, Canvas::BOLD);
        $write(
            $left,
            $y++,
            sprintf('%s · %d arquivos · recorde %d', $scene->workspace, $scene->fileCount, $scene->best),
            Theme::BREADCRUMB_FG,
            max: $scene->cols - 2 * $left,
        );
        $y++;
        $sectionTop = $y;

        $write($left, $y++, 'Iniciar', Theme::TAB_ACTIVE_FG);
        foreach ($menu->items() as $i => $action) {
            $selected = $i === $menu->selectedIndex();
            $bg = $selected ? Theme::MENU_SELECTED_BG : Theme::BG;
            if ($selected) {
                $write($left, $y, str_repeat(' ', $columnWidth), Theme::FG, $bg);
            }
            $write(
                $left,
                $y,
                ($selected ? '▶ ' : '  ') . $menu->label($action, $scene->theme),
                $selected ? Theme::TAB_ACTIVE_FG : Theme::LINK_FG,
                $bg,
                max: $columnWidth - 7,
            );
            $hint = $selected ? 'Enter' : $menu->shortcut($action);
            $write($left + $columnWidth - mb_strlen($hint) - 1, $y, $hint, Theme::KEY_FG, $bg);
            $y++;
        }
        $y++;

        $write($left, $y++, 'Atalhos', Theme::TAB_ACTIVE_FG);
        foreach (self::SHORTCUTS as [$keys, $description]) {
            $write($left + 2, $y, $keys, Theme::KEY_FG);
            $write($left + 2 + self::MENU_KEY_WIDTH, $y, $description, Theme::FG, max: $columnWidth - self::MENU_KEY_WIDTH - 2);
            $y++;
        }
        $leftEnd = $y;

        $x = $twoColumns ? $left + $columnWidth + 3 : $left;
        $y = $twoColumns ? $sectionTop : $y + 1;
        $legendWidth = $scene->cols - $x - 2 - self::MENU_SAMPLE_WIDTH - 1;
        $write($x, $y++, 'Como funciona', Theme::TAB_ACTIVE_FG);
        foreach ($this->legend($scene->theme) as [$sample, $fg, $bg, $fx, $description]) {
            $write($x + 2, $y, $sample, $fg, $bg, $fx);
            $write($x + 2 + self::MENU_SAMPLE_WIDTH, $y, $description, Theme::FG, max: $legendWidth);
            $y++;
        }

        if (max($leftEnd, $y) < $bottom - 1) {
            $write(
                $left,
                $bottom - 1,
                'Dica: snakecode /pasta/do/projeto usa o SEU código como fundo',
                Theme::GUTTER_FG,
                Theme::BG,
                Canvas::ITALIC,
                $scene->cols - 2 * $left,
            );
        }
    }

    /**
     * @return list<array{0: string, 1: int, 2: int, 3: int, 4: string}> [amostra, fg, bg, atributos, descrição]
     */
    private function legend(Theme $theme): array
    {
        return [
            ['a', $theme->headFg(), $theme->headBg(), 0, 'cursor = cabeça'],
            ['bcd', $theme->bodyFg() ?? Theme::FG, $theme->bodyBg(), 0, 'seleção = corpo'],
            [$theme->foodChar() ?? 'x', $theme->foodFg() ?? Theme::FG, Theme::BG, Canvas::CURLY | Canvas::UNDERLINE, 'sublinhado = comida'],
            ['● Ln/Col', Theme::BREAKPOINT, Theme::BG, 0, 'onde está a comida'],
            ['▸ ⋯ 8 lines', Theme::FOLD_FG, Theme::FOLD_BG, 0, 'obstáculo (nível 3+)'],
            ['⎇ level-2', Theme::STATUS_FG, Theme::STATUS_BG, 0, 'nível · ✓ n/m = meta'],
            ['User.php ×', Theme::TAB_ACTIVE_FG, Theme::TAB_BAR_BG, 0, 'curva abre outra aba'],
            ['Fatal error', Theme::PANEL_ERROR, Theme::PANEL_BG, 0, 'bateu: Enter reinicia'],
        ];
    }

    private function drawPanel(Canvas $canvas, Scene $scene): void
    {
        [, $boardRows] = self::boardSize($scene->cols, $scene->rows);
        $height = min(self::PANEL_MAX_ROWS, $boardRows - 2);
        if ($height < 3 || $scene->crash === null) {
            return;
        }

        $top = self::EDITOR_TOP + $boardRows - $height;
        $canvas->fillRow($top, Theme::PANEL_HEADER_BG);
        $x = $canvas->text(1, $top, 'PROBLEMS   OUTPUT   DEBUG CONSOLE   ', Theme::TAB_FG, Theme::PANEL_HEADER_BG);
        $canvas->text($x, $top, 'TERMINAL', Theme::TAB_ACTIVE_FG, Theme::PANEL_HEADER_BG, Canvas::UNDERLINE);

        $lines = CrashPanel::lines($scene->crash, $scene->root, $scene->state->score, $scene->best, $height - 1);
        for ($i = 1; $i < $height; $i++) {
            $canvas->fillRow($top + $i, Theme::PANEL_BG);
            [$text, $color] = $lines[$i - 1] ?? ['', Theme::FG];
            $canvas->text(1, $top + $i, $text, $color, Theme::PANEL_BG);
        }
    }

    private function drawWon(Canvas $canvas): void
    {
        $text = 'VOCÊ VENCEU — tela completa no nível 100!';
        $hint = 'Enter: nova partida   ·   m: menu   ·   q: sair';
        $y = intdiv($canvas->height, 2);
        $canvas->fillRow($y, Theme::MENU_SELECTED_BG);
        $canvas->text(max(1, intdiv($canvas->width - mb_strlen($text), 2)), $y, $text, Theme::TAB_ACTIVE_FG, Theme::MENU_SELECTED_BG, Canvas::BOLD);
        if ($y + 1 < $canvas->height - 1) $canvas->text(max(1, intdiv($canvas->width - mb_strlen($hint), 2)), $y + 1, $hint, Theme::FG);
    }

    private function drawSettings(Canvas $canvas, Scene $scene): void
    {
        [, $boardRows] = self::boardSize($scene->cols, $scene->rows);
        $bottom = self::EDITOR_TOP + $boardRows;
        for ($y = self::EDITOR_TOP; $y < $bottom; $y++) $canvas->fillRow($y, Theme::BG);
        $settings = $scene->settings;
        if ($settings === null) return;
        $x = 3; $y = self::EDITOR_TOP + 1;
        $canvas->text($x, $y++, 'Configurações', Theme::TAB_ACTIVE_FG, Theme::BG, Canvas::BOLD);
        $canvas->text($x, $y++, 'Use ←/→ para ajustar · Enter para editar a pasta · Esc para voltar', Theme::BREADCRUMB_FG);
        $y++;
        $rows = [
            sprintf('Nível inicial: %d (1–100)', $settings->startLevel),
            'Pasta do projeto: ' . ($settings->projectPath ?? '(código do SnakeCode)'),
            sprintf('Arquivos no projeto: %d (1–500)', $settings->fileCount),
            sprintf('Curvas antes de trocar de arquivo: %d', $settings->turnsPerFile),
            'Cor da cobra: ' . $settings->snakeColor,
        ];
        foreach ($rows as $i => $label) {
            $selected = $i === $scene->settingsSelection && $scene->mode === Mode::Settings;
            $bg = $selected ? Theme::MENU_SELECTED_BG : Theme::BG;
            $canvas->text($x, $y++, ($selected ? '▶ ' : '  ') . $label, $selected ? Theme::TAB_ACTIVE_FG : Theme::FG, $bg);
        }
        $y++;
        if ($scene->mode === Mode::SettingsPath) {
            $canvas->text($x, $y++, 'Caminho da pasta (Enter confirma, Esc cancela):', Theme::TAB_ACTIVE_FG);
            $canvas->text($x, $y, '> ' . $scene->pathInput, Theme::FG);
        }
        if ($bottom - 1 > $y) $canvas->text($x, $bottom - 1, 'Nível 100: velocidade máxima; a vitória exige preencher cada célula do tabuleiro.', Theme::GUTTER_FG);
    }

    private function drawStatus(Canvas $canvas, Scene $scene): void
    {
        $y = $scene->rows - 1;
        $bg = $scene->mode === Mode::Paused ? Theme::STATUS_DEBUG_BG : Theme::STATUS_BG;
        $canvas->fillRow($y, $bg, Theme::STATUS_FG);

        if ($scene->mode === Mode::Panic && $scene->editor !== null) {
            $editor = $scene->editor;
            $status = sprintf(' EDITOR  Ln %d, Col %d  %s  Esc descarta e volta', $editor->row() + 1, $editor->column() + 1, $editor->dirty() ? '● rascunho' : 'sem alterações');
            $canvas->text(0, $y, mb_substr($status, 0, $scene->cols), Theme::STATUS_FG, $bg);
            return;
        }
        if ($scene->mode === Mode::Settings || $scene->mode === Mode::SettingsPath) {
            $canvas->text(0, $y, mb_substr(' CONFIGURAÇÕES  ←/→ ajusta · Enter edita caminho · Esc volta ', 0, $scene->cols), Theme::STATUS_FG, $bg);
            return;
        }

        $state = $scene->state;
        $showGame = $scene->showsGame();
        $food = $showGame ? $state->food : null;

        $left = sprintf(
            ' ⎇ %s  ⟳ %d↓ %d↑  ⊗ 0  ⚠ %d ',
            $state->level->branch(),
            $scene->deaths,
            $showGame ? $state->score : 0,
            $food !== null ? 1 : 0,
        );
        if ($scene->mode === Mode::Paused) {
            $left .= ' ⏸ Paused on breakpoint ';
        }
        if ($scene->toast !== null && $showGame) {
            $left .= ' ' . $scene->toast . ' ';
        }
        $leftEnd = $canvas->text(0, $y, $left, Theme::STATUS_FG, $bg);

        $line = $scene->startLine + ($food !== null ? $food->y : 0) + 1;
        $col = $food !== null ? $food->x + 1 : 1;
        $progress = $showGame ? sprintf('✓ %d/%d', $state->eatenInLevel, $state->level->foodsToNext) : '✓ Prettier';

        $right = sprintf('Ln %d, Col %d   Spaces: 4   UTF-8   LF   {} %s   %s ', $line, $col, $scene->file->language, $progress);
        if ($scene->cols - mb_strlen($right) < $leftEnd) {
            $right = sprintf('Ln %d, Col %d  %s ', $line, $col, $progress);
        }
        // Ln/Col é informação de jogo: se não couber, sobrepõe o fim da parte esquerda.
        $canvas->text(max(0, $scene->cols - mb_strlen($right)), $y, $right, Theme::STATUS_FG, $bg);
    }
}
