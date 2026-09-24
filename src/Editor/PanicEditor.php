<?php

declare(strict_types=1);

namespace SnakeCode\Editor;

/** Buffer simples para editar o arquivo ativo enquanto o jogo está escondido. */
final class PanicEditor
{
    /** @var list<string> */
    private array $lines;
    private int $row;
    private int $column;
    private bool $dirty = false;

    public function __construct(string $code, int $row = 0, int $column = 0)
    {
        $this->lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $code));
        $this->row = max(0, min(count($this->lines) - 1, $row));
        $this->column = max(0, min(mb_strlen($this->lines[$this->row]), $column));
    }

    public function text(): string { return implode("\n", $this->lines); }
    public function row(): int { return $this->row; }
    public function column(): int { return $this->column; }
    public function dirty(): bool { return $this->dirty; }

    public function move(string $key): void
    {
        if ($key === 'left') {
            if ($this->column > 0) $this->column--;
            elseif ($this->row > 0) { $this->row--; $this->column = mb_strlen($this->lines[$this->row]); }
        } elseif ($key === 'right') {
            if ($this->column < mb_strlen($this->lines[$this->row])) $this->column++;
            elseif ($this->row < count($this->lines) - 1) { $this->row++; $this->column = 0; }
        } elseif ($key === 'up' && $this->row > 0) {
            $this->row--; $this->column = min($this->column, mb_strlen($this->lines[$this->row]));
        } elseif ($key === 'down' && $this->row < count($this->lines) - 1) {
            $this->row++; $this->column = min($this->column, mb_strlen($this->lines[$this->row]));
        } elseif ($key === 'home') $this->column = 0;
        elseif ($key === 'end') $this->column = mb_strlen($this->lines[$this->row]);
    }

    public function insert(string $text): void
    {
        if ($text === "\n") {
            $parts = mb_str_split($this->lines[$this->row]);
            $before = implode('', array_slice($parts, 0, $this->column));
            $after = implode('', array_slice($parts, $this->column));
            array_splice($this->lines, $this->row, 1, [$before, $after]);
            $this->row++; $this->column = 0;
        } elseif ($text === "\t") {
            $this->insert('    ');
        } elseif ($text !== '') {
            $parts = mb_str_split($this->lines[$this->row]);
            array_splice($parts, $this->column, 0, mb_str_split($text));
            $this->lines[$this->row] = implode('', $parts);
            $this->column += mb_strlen($text);
        }
        $this->dirty = true;
    }

    public function backspace(): void
    {
        if ($this->column > 0) {
            $parts = mb_str_split($this->lines[$this->row]);
            array_splice($parts, --$this->column, 1);
            $this->lines[$this->row] = implode('', $parts);
        } elseif ($this->row > 0) {
            $priorLength = mb_strlen($this->lines[$this->row - 1]);
            $this->lines[$this->row - 1] .= $this->lines[$this->row];
            array_splice($this->lines, $this->row, 1);
            $this->row--; $this->column = $priorLength;
        } else return;
        $this->dirty = true;
    }

    public function delete(): void
    {
        $parts = mb_str_split($this->lines[$this->row]);
        if ($this->column < count($parts)) {
            array_splice($parts, $this->column, 1);
            $this->lines[$this->row] = implode('', $parts);
        } elseif ($this->row < count($this->lines) - 1) {
            $this->lines[$this->row] .= $this->lines[$this->row + 1];
            array_splice($this->lines, $this->row + 1, 1);
        } else return;
        $this->dirty = true;
    }

}
