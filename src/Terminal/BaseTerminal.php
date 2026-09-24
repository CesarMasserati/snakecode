<?php

declare(strict_types=1);

namespace SnakeCode\Terminal;

/**
 * Comportamento comum aos terminais: escrita segura do frame, título e controle de tamanho.
 */
abstract class BaseTerminal implements Terminal
{
    private const WRITE_TIMEOUT_NS = 2_000_000_000;
    private const SIZE_POLL_NS = 1_000_000_000;

    protected int $cols = 80;
    protected int $rows = 24;
    protected bool $interrupted = false;
    protected bool $resized = false;
    private int $lastSizePoll = 0;

    /**
     * Relê o tamanho real da janela.
     */
    abstract protected function refreshSize(): void;

    /**
     * true quando o SO avisa mudanças de tamanho de forma confiável (dispensa polling).
     */
    protected function resizeIsSignalled(): bool
    {
        return false;
    }

    /**
     * Grava o frame inteiro, tratando gravações parciais: um frame cortado deixaria
     * as últimas linhas da tela com o conteúdo antigo.
     */
    public function write(string $bytes): void
    {
        $deadline = hrtime(true) + self::WRITE_TIMEOUT_NS;

        while ($bytes !== '') {
            $written = @fwrite(STDOUT, $bytes);
            if ($written === false) {
                return; // Console fechado: não há para onde escrever.
            }
            if ($written > 0) {
                $bytes = substr($bytes, $written);
                continue;
            }
            if (hrtime(true) >= $deadline) {
                return; // Terminal travado: desiste deste frame, o próximo redesenha tudo.
            }
            usleep(10_000);
        }

        fflush(STDOUT);
    }

    public function title(string $title): void
    {
        $this->write("\e]0;" . preg_replace('/[\x00-\x1f\x7f]/', '', $title) . "\x07");
    }

    public function size(): array
    {
        return [$this->cols, $this->rows];
    }

    public function interrupted(): bool
    {
        return $this->interrupted;
    }

    public function consumeResize(): bool
    {
        if ($this->resized) {
            $this->resized = false;
            $this->refreshSize();

            return true;
        }
        if ($this->resizeIsSignalled()) {
            return false;
        }

        // Sem aviso do SO: consulta o tamanho no máximo uma vez por segundo.
        $now = hrtime(true);
        if ($now - $this->lastSizePoll < self::SIZE_POLL_NS) {
            return false;
        }
        $this->lastSizePoll = $now;
        $before = [$this->cols, $this->rows];
        $this->refreshSize();

        return $before !== [$this->cols, $this->rows];
    }
}
