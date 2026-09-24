<?php

declare(strict_types=1);

namespace SnakeCode\Editor;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Seleciona os arquivos de código "de verdade" de um projeto para servir de fundo.
 *
 * 1. Em repositórios git usa `git ls-files` (versionados + novos não ignorados),
 *    que já respeita o .gitignore. Fora do git, varre a pasta podando dependências,
 *    builds, caches e pastas ocultas sem descer nelas.
 * 2. Mantém só extensões de código (ver Language), descartando mídia, documentos,
 *    lock files, arquivos minificados/gerados e arquivos de segredo.
 * 3. Retorna os editados mais recentemente primeiro, limitados a $limit.
 */
final class ProjectScanner
{
    public const DEFAULT_LIMIT = 40;

    private const MAX_FILE_BYTES = 131_072;
    /** Teto de entradas visitadas na varredura manual (protege contra pastas gigantes). */
    private const MAX_WALK_ENTRIES = 50_000;

    private const IGNORED_DIRS = [
        'vendor', 'node_modules', 'bower_components', 'jspm_packages', 'storage', 'cache', 'dist', 'build',
        'coverage', 'tmp', 'temp', 'log', 'logs', 'target', 'obj', '__pycache__', 'venv', 'Pods', 'DerivedData',
    ];

    /** Minificados, bundles, source maps, typings e helpers gerados. */
    private const GENERATED = '/(\.min\.[a-z]+$|\.bundle\.|\.chunk\.|\.d\.ts$|^_ide_helper)/i';

    /** Nunca exibir na tela algo que pareça conter credenciais. */
    private const SENSITIVE = '/(^|[._-])(secrets?|credentials?|passwords?)([._-]|$)|^\.env/i';

    /**
     * @return list<string> caminhos absolutos, do mais recentemente editado para o mais antigo
     */
    public function scan(string $directory, int $limit = self::DEFAULT_LIMIT): array
    {
        $directory = rtrim(self::normalize($directory), '/');
        $candidates = $this->fromGit($directory);
        if ($candidates === null || $candidates === []) {
            $candidates = $this->walk($directory);
        }

        $modified = [];
        foreach ($candidates as $path) {
            $path = self::normalize($path);
            if ($this->accepts($directory, $path)) {
                $modified[$path] = (int) @filemtime($path);
            }
        }

        // Ordenação estável: empates mantêm a ordem de descoberta.
        arsort($modified);

        return array_slice(array_keys($modified), 0, max(1, $limit));
    }

    private function accepts(string $directory, string $path): bool
    {
        $relative = substr($path, strlen($directory) + 1);
        $segments = explode('/', $relative);
        $name = array_pop($segments);

        foreach ($segments as $segment) {
            if (self::isIgnoredDirectory($segment)) {
                return false;
            }
        }

        if (
            Language::fromPath($name) === null
            || preg_match(self::GENERATED, $name) === 1
            || preg_match(self::SENSITIVE, $name) === 1
            || !is_file($path)
            || !is_readable($path)
        ) {
            return false;
        }

        $size = (int) @filesize($path);

        return $size > 0 && $size <= self::MAX_FILE_BYTES;
    }

    /**
     * Separador "/" em todos os caminhos (no Windows o iterador devolve "\").
     */
    private static function normalize(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function nullDevice(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    }

    private static function isIgnoredDirectory(string $name): bool
    {
        return str_starts_with($name, '.') || in_array($name, self::IGNORED_DIRS, true);
    }

    /**
     * @return list<string>|null null quando não é um repositório git (ou o git não está disponível)
     */
    private function fromGit(string $directory): ?array
    {
        if (!function_exists('proc_open')) {
            return null;
        }

        $process = @proc_open(
            ['git', '-C', $directory, 'ls-files', '-z', '--cached', '--others', '--exclude-standard'],
            [0 => ['file', self::nullDevice(), 'r'], 1 => ['pipe', 'w'], 2 => ['file', self::nullDevice(), 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            return null;
        }

        $output = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        if (proc_close($process) !== 0) {
            return null;
        }

        $paths = array_filter(explode("\0", $output), static fn (string $path): bool => $path !== '');

        return array_values(array_map(static fn (string $path): string => $directory . '/' . $path, $paths));
    }

    /**
     * @return list<string>
     */
    private function walk(string $directory): array
    {
        $filtered = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            static fn (SplFileInfo $entry): bool => !$entry->isDir()
                || ($entry->isReadable() && !self::isIgnoredDirectory($entry->getFilename())),
        );
        $iterator = new RecursiveIteratorIterator(
            $filtered,
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD,
        );

        $paths = [];
        $visited = 0;
        foreach ($iterator as $entry) {
            /** @var SplFileInfo $entry */
            if (++$visited > self::MAX_WALK_ENTRIES) {
                break;
            }
            if ($entry->isFile()) {
                $paths[] = $entry->getPathname();
            }
        }

        return $paths;
    }
}
