<?php

declare(strict_types=1);

namespace SnakeCode\Editor;

/**
 * Acumula texto colorido numa grade de linhas com exatamente uma coluna por caractere.
 */
final class LineBuffer
{
    private const TAB_WIDTH = 4;

    /** @var list<array{0: list<string>, 1: list<int>}> */
    private array $lines = [[[], []]];
    private int $row = 0;

    public function append(string $text, int $color): void
    {
        foreach (mb_str_split($text) as $char) {
            if ($char === "\n") {
                $this->lines[++$this->row] = [[], []];
                continue;
            }
            if ($char === "\t") {
                for ($n = 0; $n < self::TAB_WIDTH; $n++) {
                    $this->lines[$this->row][0][] = ' ';
                    $this->lines[$this->row][1][] = $color;
                }
                continue;
            }
            $this->lines[$this->row][0][] = self::cellSafe($char);
            $this->lines[$this->row][1][] = $color;
        }
    }

    /**
     * Número (1-based) da linha que está sendo escrita.
     */
    public function lineNumber(): int
    {
        return $this->row + 1;
    }

    /**
     * @return list<array{0: list<string>, 1: list<int>}>
     */
    public function lines(): array
    {
        $lines = $this->lines;
        // Arquivo terminado em "\n" não tem uma última linha real.
        if (count($lines) > 1 && $lines[array_key_last($lines)][0] === []) {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * Garante uma coluna por caractere, preservando o alinhamento do grid.
     */
    private static function cellSafe(string $char): string
    {
        if (strlen($char) === 1) {
            return ($char < ' ' || $char === "\x7f") ? ' ' : $char;
        }

        return mb_strwidth($char) === 1 ? $char : '?';
    }
}
