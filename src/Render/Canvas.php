<?php

declare(strict_types=1);

namespace SnakeCode\Render;

/**
 * Buffer de células (caractere + cor de frente + fundo + atributos) serializado em ANSI.
 * O frame inteiro é emitido de uma vez, com posicionamento absoluto por linha: sem flicker.
 */
final class Canvas
{
    public const BOLD = 1;
    public const ITALIC = 2;
    public const UNDERLINE = 4;
    /** Sublinhado ondulado (estilo linter); UNDERLINE simples é o fallback. */
    public const CURLY = 8;

    /** @var list<list<string>> */
    private array $chars;
    /** @var list<list<int>> */
    private array $fg;
    /** @var list<list<int>> */
    private array $bg;
    /** @var list<list<int>> */
    private array $fx;

    public readonly int $width;
    public readonly int $height;

    public function __construct(int $width, int $height, int $fg, int $bg)
    {
        $this->width = max(1, $width);
        $this->height = max(1, $height);
        $this->chars = array_fill(0, $this->height, array_fill(0, $this->width, ' '));
        $this->fg = array_fill(0, $this->height, array_fill(0, $this->width, $fg));
        $this->bg = array_fill(0, $this->height, array_fill(0, $this->width, $bg));
        $this->fx = array_fill(0, $this->height, array_fill(0, $this->width, 0));
    }

    public function fillRow(int $y, int $bg, int $fg = Theme::FG): void
    {
        if ($y < 0 || $y >= $this->height) {
            return;
        }
        $this->chars[$y] = array_fill(0, $this->width, ' ');
        $this->fg[$y] = array_fill(0, $this->width, $fg);
        $this->bg[$y] = array_fill(0, $this->width, $bg);
        $this->fx[$y] = array_fill(0, $this->width, 0);
    }

    /**
     * Atualiza uma célula; argumentos null preservam o valor atual.
     */
    public function put(int $x, int $y, ?string $char, ?int $fg = null, ?int $bg = null, ?int $fx = null): void
    {
        if ($x < 0 || $y < 0 || $x >= $this->width || $y >= $this->height) {
            return;
        }
        if ($char !== null) {
            $this->chars[$y][$x] = $char;
        }
        if ($fg !== null) {
            $this->fg[$y][$x] = $fg;
        }
        if ($bg !== null) {
            $this->bg[$y][$x] = $bg;
        }
        if ($fx !== null) {
            $this->fx[$y][$x] = $fx;
        }
    }

    /**
     * Escreve texto (cortado na borda direita).
     *
     * @return int coluna seguinte ao texto
     */
    public function text(int $x, int $y, string $text, ?int $fg = null, ?int $bg = null, int $fx = 0): int
    {
        foreach (mb_str_split($text) as $char) {
            if ($x >= $this->width) {
                break;
            }
            $this->put($x, $y, $char, $fg, $bg, $fx);
            $x++;
        }

        return $x;
    }

    public function toAnsi(): string
    {
        $out = '';
        for ($y = 0; $y < $this->height; $y++) {
            $out .= "\e[" . ($y + 1) . ';1H';
            $previous = null;
            for ($x = 0; $x < $this->width; $x++) {
                $style = [$this->fg[$y][$x], $this->bg[$y][$x], $this->fx[$y][$x]];
                if ($style !== $previous) {
                    $out .= self::sgr(...$style);
                    $previous = $style;
                }
                $out .= $this->chars[$y][$x];
            }
        }

        return $out . "\e[0m";
    }

    /**
     * Texto puro do canvas (sem ANSI), útil em testes e diagnósticos.
     */
    public function toPlainText(): string
    {
        return implode("\n", array_map(static fn (array $row): string => implode('', $row), $this->chars));
    }

    private static function sgr(int $fg, int $bg, int $fx): string
    {
        $codes = '0';
        if ($fx & self::BOLD) {
            $codes .= ';1';
        }
        if ($fx & self::ITALIC) {
            $codes .= ';3';
        }
        if ($fx & (self::UNDERLINE | self::CURLY)) {
            $codes .= ';4';
        }

        $sequence = "\e[{$codes};38;5;{$fg};48;5;{$bg}m";
        if ($fx & self::CURLY) {
            $sequence .= "\e[4:3m\e[58:5:" . Theme::CURL . 'm';
        }

        return $sequence;
    }
}
