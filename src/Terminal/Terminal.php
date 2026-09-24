<?php

declare(strict_types=1);

namespace SnakeCode\Terminal;

/**
 * Terminal do sistema operacional: modo raw, tela alternativa, tamanho e teclado.
 *
 * Implementações: PosixTerminal (Linux, macOS, WSL) e WindowsTerminal (console nativo).
 */
interface Terminal
{
    public function enter(): void;

    /**
     * Devolve o terminal ao estado original. Idempotente.
     */
    public function restore(): void;

    public function write(string $bytes): void;

    public function title(string $title): void;

    /**
     * @return array{0: int, 1: int} colunas e linhas
     */
    public function size(): array;

    public function interrupted(): bool;

    /**
     * Informa (uma única vez) se a janela mudou de tamanho desde a última consulta.
     */
    public function consumeResize(): bool;

    /**
     * Aguarda até $timeoutUs microssegundos por teclas e devolve os bytes lidos
     * ('' se nada), no formato de um terminal VT: setas chegam como "\e[A".."\e[D".
     */
    public function read(int $timeoutUs): string;
}
