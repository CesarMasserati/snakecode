<?php

declare(strict_types=1);

namespace SnakeCode\Storage;

/**
 * Persistência local (recorde e perfil de discrição) em JSON.
 *
 * - Diretório 0700 e arquivo 0600 (dado pessoal do usuário).
 * - Escrita atômica: arquivo temporário no mesmo diretório + rename().
 * - Read-modify-write sob flock, seguro com várias instâncias abertas.
 * - Falhas de I/O nunca derrubam o jogo: o recorde apenas deixa de ser salvo.
 */
final class StateStore
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    public static function default(): self
    {
        // Linux: ~/.local/state/snakecode · Windows: %LOCALAPPDATA%\snakecode
        $base = getenv('XDG_STATE_HOME') ?: (PHP_OS_FAMILY === 'Windows'
            ? (getenv('LOCALAPPDATA') ?: sys_get_temp_dir())
            : (getenv('HOME') ?: sys_get_temp_dir()) . '/.local/state');

        return new self($base . '/snakecode/state.json');
    }

    /**
     * @return array<string, mixed>
     */
    public function load(): array
    {
        if (!is_file($this->path)) {
            return [];
        }

        $data = json_decode((string) @file_get_contents($this->path), true);

        return is_array($data) ? $data : [];
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $mutator
     */
    public function update(callable $mutator): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            return;
        }

        $lock = @fopen($directory . '/.lock', 'c');
        if ($lock === false) {
            return;
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                return;
            }

            $temporary = @tempnam($directory, '.state-');
            if ($temporary === false) {
                return;
            }

            $json = json_encode($mutator($this->load()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($json === false || @file_put_contents($temporary, $json . "\n") === false) {
                @unlink($temporary);

                return;
            }

            @chmod($temporary, 0600);
            if (!@rename($temporary, $this->path)) {
                @unlink($temporary);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
