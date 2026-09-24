<?php

declare(strict_types=1);

namespace SnakeCode\Render;

use InvalidArgumentException;

/**
 * Paleta (256 cores) da IDE e perfis de discrição da cobra.
 *
 * - subtle: corpo é só um fundo levemente mais claro; cabeça é o cursor em bloco.
 * - medium: corpo com cor de seleção de texto.
 * - easy:   cores fortes, para jogar sem esforço.
 */
final readonly class Theme
{
    public const FG = 252;
    public const BG = 234;
    public const LINE_HIGHLIGHT = 235;
    public const GUTTER_FG = 240;
    public const GUTTER_ACTIVE_FG = 250;
    public const TAB_BAR_BG = 236;
    public const TAB_FG = 244;
    public const TAB_ACTIVE_FG = 255;
    public const TAB_BORDER = 238;
    public const BREADCRUMB_FG = 245;
    public const STATUS_BG = 25;
    public const STATUS_DEBUG_BG = 166;
    public const STATUS_FG = 255;
    public const BREAKPOINT = 160;
    public const HINT_FG = 167;
    public const FOLD_BG = 237;
    public const FOLD_FG = 246;
    public const CURL = 196;
    public const PANEL_BG = 233;
    public const PANEL_HEADER_BG = 235;
    public const PANEL_PROMPT = 114;
    public const PANEL_ERROR = 203;
    public const PANEL_TRACE = 245;
    public const MENU_SELECTED_BG = 24;
    public const LINK_FG = 75;
    public const KEY_FG = 244;

    public const PROFILES = ['subtle', 'medium', 'easy'];

    /** @var array<string, array{body_bg: int, body_fg: int|null, head_bg: int, head_fg: int, food_fg: int|null, food_char: string|null}> */
    private const STYLES = [
        'subtle' => ['body_bg' => 236, 'body_fg' => null, 'head_bg' => 250, 'head_fg' => 234, 'food_fg' => null, 'food_char' => null],
        'medium' => ['body_bg' => 24, 'body_fg' => null, 'head_bg' => 252, 'head_fg' => 234, 'food_fg' => 210, 'food_char' => null],
        'easy' => ['body_bg' => 28, 'body_fg' => 16, 'head_bg' => 46, 'head_fg' => 16, 'food_fg' => 196, 'food_char' => '●'],
    ];

    public function __construct(
        public string $profile = 'subtle',
    ) {
        if (!in_array($profile, self::PROFILES, true)) {
            throw new InvalidArgumentException(sprintf('Perfil de discrição desconhecido: %s', $profile));
        }
    }

    public function next(): self
    {
        return $this->shifted(1);
    }

    /**
     * Perfil vizinho na lista (circular): +1 avança, -1 volta.
     */
    public function shifted(int $step): self
    {
        $count = count(self::PROFILES);
        $index = (int) array_search($this->profile, self::PROFILES, true);

        return new self(self::PROFILES[(($index + $step) % $count + $count) % $count]);
    }

    public function bodyBg(): int
    {
        return self::STYLES[$this->profile]['body_bg'];
    }

    public function bodyFg(): ?int
    {
        return self::STYLES[$this->profile]['body_fg'];
    }

    public function headBg(): int
    {
        return self::STYLES[$this->profile]['head_bg'];
    }

    public function headFg(): int
    {
        return self::STYLES[$this->profile]['head_fg'];
    }

    public function foodFg(): ?int
    {
        return self::STYLES[$this->profile]['food_fg'];
    }

    public function foodChar(): ?string
    {
        return self::STYLES[$this->profile]['food_char'];
    }
}
