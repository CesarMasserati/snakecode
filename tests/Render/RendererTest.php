<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Render;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use SnakeCode\Domain\GameState;
use SnakeCode\Editor\SourceFile;
use SnakeCode\Mode;
use SnakeCode\Render\Renderer;
use SnakeCode\Render\Scene;
use SnakeCode\Render\Theme;
use SnakeCode\Ui\StartMenu;

final class RendererTest extends TestCase
{
    /**
     * @return iterable<string, array{0: int, 1: int}>
     */
    public static function sizes(): iterable
    {
        yield 'mínimo 60x16' => [60, 16];
        yield 'padrão 80x24' => [80, 24];
        yield 'grande 160x45' => [160, 45];
    }

    #[DataProvider('sizes')]
    public function testMenuFitsAndKeepsTheStatusbar(int $cols, int $rows): void
    {
        $lines = $this->render(Mode::Menu, $cols, $rows, new StartMenu());

        self::assertCount($rows, $lines);
        foreach ($lines as $line) {
            self::assertSame($cols, mb_strlen($line));
        }
        self::assertStringContainsString('Welcome ×', $lines[0]);
        $text = implode("\n", $lines);
        foreach (['Iniciar', 'Discrição: ◀ subtle ▶', 'Sair'] as $option) {
            self::assertStringContainsString($option, $text);
        }
        self::assertStringContainsString('feature/level-1', $lines[$rows - 1]);
    }

    public function testMenuShowsHelpSectionsSideBySide(): void
    {
        $text = implode("\n", $this->render(Mode::Menu, 80, 24, new StartMenu()));

        foreach (['Atalhos', 'Como funciona', 'cursor = cabeça', 'sublinhado = comida', 'PÂNICO: só código'] as $help) {
            self::assertStringContainsString($help, $text);
        }
    }

    public function testGameFramePointsToTheFood(): void
    {
        $lines = $this->render(Mode::Running, 80, 24);
        $food = $this->state->food;

        self::assertNotNull($food);
        self::assertStringContainsString(sprintf('Ln %d, Col %d', $food->y + 1, $food->x + 1), $lines[23]);
        self::assertStringContainsString('●', $lines[2 + $food->y]);
    }

    private GameState $state;

    /**
     * @return list<string>
     */
    private function render(Mode $mode, int $cols, int $rows, ?StartMenu $menu = null): array
    {
        [$boardCols, $boardRows] = Renderer::boardSize($cols, $rows);
        $this->state = new GameState($boardCols, $boardRows, new Randomizer(new Mt19937(1)));

        $scene = new Scene(
            cols: $cols,
            rows: $rows,
            state: $this->state,
            file: new SourceFile([[['<', '?', 'p', 'h', 'p'], [75, 75, 75, 75, 75]]], [], []),
            path: 'src/App.php',
            startLine: 0,
            tabs: ['src/App.php'],
            theme: new Theme(),
            mode: $mode,
            crash: null,
            best: 120,
            deaths: 0,
            toast: null,
            tooSmall: false,
            root: '/app',
            menu: $menu,
            workspace: 'demo',
            fileCount: 40,
        );

        return explode("\n", (new Renderer())->canvas($scene)->toPlainText());
    }
}
