<?php

declare(strict_types=1);

namespace SnakeCode\Render;

use SnakeCode\Domain\GameState;
use SnakeCode\Editor\SourceFile;
use SnakeCode\Mode;
use SnakeCode\Ui\StartMenu;
use Throwable;

/**
 * Tudo o que o Renderer precisa para desenhar um frame.
 */
final readonly class Scene
{
    /**
     * @param list<string> $tabs caminhos relativos, aba ativa primeiro
     */
    public function __construct(
        public int $cols,
        public int $rows,
        public GameState $state,
        public SourceFile $file,
        public string $path,
        public int $startLine,
        public array $tabs,
        public Theme $theme,
        public Mode $mode,
        public ?Throwable $crash,
        public int $best,
        public int $deaths,
        public ?string $toast,
        public bool $tooSmall,
        public string $root,
        public ?StartMenu $menu = null,
        public string $workspace = '',
        public int $fileCount = 0,
        public ?\SnakeCode\Editor\PanicEditor $editor = null,
        public ?\SnakeCode\GameSettings $settings = null,
        public string $pathInput = '',
        public int $settingsSelection = 0,
        public int $startColumn = 0,
    ) {
    }

    /**
     * No pânico nada do jogo aparece (só código); no menu, a aba Welcome ocupa o editor.
     */
    public function showsGame(): bool
    {
        return $this->mode !== Mode::Panic && $this->mode !== Mode::Menu;
    }
}
