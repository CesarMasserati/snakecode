<?php

declare(strict_types=1);

namespace SnakeCode\Terminal;

use RuntimeException;

/**
 * Terminal POSIX (Linux, macOS, WSL) controlado via stty.
 *
 * A restauração é garantida por três caminhos independentes: try/finally no Game,
 * register_shutdown_function (erros fatais/exit) e handlers de SIGTERM/SIGHUP.
 * Ctrl+C chega como byte (\x03) porque o modo raw desliga "isig".
 */
final class PosixTerminal extends BaseTerminal
{
    /** Espera extra para completar uma sequência de escape que chegou fatiada. */
    private const ESCAPE_GRACE_US = 25_000;

    private ?string $savedMode = null;
    private bool $active = false;
    private bool $hasSignals = false;

    public function enter(): void
    {
        $mode = $this->stty('-g');
        if ($mode === null || $mode === '') {
            throw new RuntimeException('Não foi possível ler o modo atual do terminal (stty -g).');
        }

        $this->savedMode = $mode;
        $this->active = true;
        register_shutdown_function([$this, 'restore']);
        $this->installSignalHandlers();

        // "min 0 time 0" já torna a leitura não bloqueante no nível do termios.
        // NÃO usar stream_set_blocking(STDIN, false): STDIN e STDOUT compartilham o mesmo
        // descritor do TTY, e O_NONBLOCK faria o fwrite do frame gravar só parte dos bytes
        // quando o terminal está lento (ConPTY/Windows Terminal), cortando o rodapé.
        $this->stty('-icanon -echo -isig -ixon min 0 time 0');
        stream_set_read_buffer(STDIN, 0);

        // Tela alternativa, cursor oculto, sem quebra automática de linha.
        $this->write("\e[?1049h\e[?25l\e[?7l\e[2J");
        $this->refreshSize();
    }

    public function restore(): void
    {
        if (!$this->active) {
            return;
        }
        $this->active = false;

        $this->write("\e[0m\e[?7h\e[?25h\e[?1049l");
        if ($this->savedMode !== null) {
            $this->stty(escapeshellarg($this->savedMode));
        }
    }

    public function read(int $timeoutUs): string
    {
        $read = [STDIN];
        $write = null;
        $except = null;
        // @: um sinal (ex.: SIGWINCH) interrompe o select com warning "Interrupted system call".
        if (!@stream_select($read, $write, $except, intdiv($timeoutUs, 1_000_000), $timeoutUs % 1_000_000)) {
            return '';
        }

        $bytes = (string) fread(STDIN, 256);
        if (preg_match('/\e(?:\[[0-9;]*|O)?$/', $bytes) === 1) {
            $read = [STDIN];
            if (@stream_select($read, $write, $except, 0, self::ESCAPE_GRACE_US)) {
                $bytes .= (string) fread(STDIN, 64);
            }
        }

        return $bytes;
    }

    protected function refreshSize(): void
    {
        $size = $this->stty('size');
        if ($size !== null && preg_match('/^(\d+)\s+(\d+)$/', $size, $match) === 1) {
            $this->rows = max(1, (int) $match[1]);
            $this->cols = max(1, (int) $match[2]);
        }
    }

    protected function resizeIsSignalled(): bool
    {
        return $this->hasSignals;
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal') || !function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        $stop = function (): void {
            $this->interrupted = true;
        };
        foreach ([SIGINT, SIGTERM, SIGHUP] as $signal) {
            pcntl_signal($signal, $stop);
        }
        pcntl_signal(SIGWINCH, function (): void {
            $this->resized = true;
        });
        $this->hasSignals = true;
    }

    private function stty(string $arguments): ?string
    {
        $output = shell_exec('stty ' . $arguments . ' < /dev/tty 2>/dev/null');

        return is_string($output) ? trim($output) : null;
    }
}
