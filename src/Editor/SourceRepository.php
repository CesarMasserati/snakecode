<?php

declare(strict_types=1);

namespace SnakeCode\Editor;

use RuntimeException;

/**
 * Workspace exibido no editor.
 *
 * Cada curva da cobra abre o próximo arquivo (rotação circular). Ao revisitar
 * um arquivo, o editor rola para o trecho seguinte, como quem navega pelo código.
 * Os arquivos são lidos e coloridos só quando abertos pela primeira vez.
 */
final class SourceRepository
{
    private const MAX_CACHED = 16;
    private const MAX_TABS = 4;

    /** @var list<string> caminhos absolutos na ordem de rotação */
    private readonly array $files;
    /** @var array<string, SourceFile> */
    private array $cache = [];
    /** @var array<string, int> primeira linha (0-based) exibida por arquivo */
    private array $offsets = [];
    /** @var list<string> mais recente primeiro */
    private array $tabs = [];
    private int $index = 0;

    /**
     * @param list<string> $files caminhos absolutos
     * @param string       $base  raiz do workspace: define os caminhos exibidos nas abas e no breadcrumb
     */
    public function __construct(
        array $files,
        private readonly string $base,
        private readonly Highlighter $highlighter,
    ) {
        if ($files === []) {
            throw new RuntimeException(sprintf('Nenhum arquivo de código encontrado em %s', $base));
        }

        $this->files = array_values($files);
        $this->open(0, 0);
    }

    public function current(): SourceFile
    {
        return $this->load($this->files[$this->index]);
    }

    public function startLine(): int
    {
        return $this->offsets[$this->files[$this->index]];
    }

    public function relativePath(): string
    {
        return $this->relative($this->files[$this->index]);
    }

    public function workspaceName(): string
    {
        return basename($this->base, '.phar');
    }

    public function count(): int
    {
        return count($this->files);
    }

    /**
     * @return list<string> caminhos relativos, aba ativa primeiro
     */
    public function tabs(): array
    {
        return array_map(fn (string $path): string => $this->relative($path), $this->tabs);
    }

    /**
     * Abre o próximo arquivo; se ele já foi visto, rola $height linhas adiante.
     */
    public function next(int $height): void
    {
        $index = ($this->index + 1) % count($this->files);
        $path = $this->files[$index];

        $start = isset($this->offsets[$path]) ? $this->offsets[$path] + max(1, $height) : 0;
        if ($start >= $this->load($path)->lineCount()) {
            $start = 0;
        }

        $this->open($index, $start);
    }

    private function open(int $index, int $start): void
    {
        $this->index = $index;
        $path = $this->files[$index];
        $this->offsets[$path] = $start;

        $others = array_filter($this->tabs, static fn (string $tab): bool => $tab !== $path);
        $this->tabs = array_slice([$path, ...$others], 0, self::MAX_TABS);
    }

    private function load(string $path): SourceFile
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }
        if (count($this->cache) >= self::MAX_CACHED) {
            array_shift($this->cache);
        }

        $code = @file_get_contents($path);
        $language = Language::fromPath($path) ?? Language::Php;

        return $this->cache[$path] = $code === false
            ? $this->highlighter->highlight("<?php\n\n// (file unavailable)\n")
            : $this->highlighter->highlight($code, $language);
    }

    private function relative(string $path): string
    {
        return str_starts_with($path, $this->base . '/') ? substr($path, strlen($this->base) + 1) : $path;
    }
}
