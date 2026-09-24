<?php

declare(strict_types=1);

namespace SnakeCode\Editor;

/**
 * Linguagens exibidas no editor. Também funciona como lista de permissão:
 * arquivos cuja extensão não mapeia para uma linguagem (mídia, documentos,
 * lock files, dados) nunca entram no workspace.
 */
enum Language: string
{
    case Php = 'PHP';
    case Blade = 'Blade';
    case JavaScript = 'JavaScript';
    case TypeScript = 'TypeScript';
    case Vue = 'Vue';
    case Svelte = 'Svelte';
    case Html = 'HTML';
    case Css = 'CSS';
    case Scss = 'SCSS';
    case Python = 'Python';
    case Ruby = 'Ruby';
    case Go = 'Go';
    case Rust = 'Rust';
    case Java = 'Java';
    case Kotlin = 'Kotlin';
    case CSharp = 'C#';
    case Cpp = 'C++';
    case Swift = 'Swift';
    case Sql = 'SQL';
    case Shell = 'Shell';

    public static function fromPath(string $path): ?self
    {
        $name = strtolower(basename($path));
        if (str_ends_with($name, '.blade.php')) {
            return self::Blade;
        }

        return match (pathinfo($name, PATHINFO_EXTENSION)) {
            'php' => self::Php,
            'js', 'mjs', 'cjs', 'jsx' => self::JavaScript,
            'ts', 'tsx', 'mts', 'cts' => self::TypeScript,
            'vue' => self::Vue,
            'svelte' => self::Svelte,
            'html', 'htm' => self::Html,
            'css', 'less' => self::Css,
            'scss', 'sass' => self::Scss,
            'py' => self::Python,
            'rb' => self::Ruby,
            'go' => self::Go,
            'rs' => self::Rust,
            'java' => self::Java,
            'kt', 'kts' => self::Kotlin,
            'cs' => self::CSharp,
            'c', 'h', 'cc', 'cpp', 'cxx', 'hpp' => self::Cpp,
            'swift' => self::Swift,
            'sql' => self::Sql,
            'sh', 'bash', 'zsh' => self::Shell,
            default => null,
        };
    }

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Arquivos de marcação: tags, atributos e seções <script>/<style>.
     */
    public function isMarkup(): bool
    {
        return match ($this) {
            self::Html, self::Blade, self::Vue, self::Svelte => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public function lineComments(): array
    {
        return match ($this) {
            self::Python, self::Ruby, self::Shell => ['#'],
            self::Sql => ['--'],
            self::Css, self::Html, self::Blade, self::Vue, self::Svelte => [],
            default => ['//'],
        };
    }

    /**
     * @return list<array{0: string, 1: string}> pares [abertura, fechamento]
     */
    public function blockComments(): array
    {
        return match ($this) {
            self::Python, self::Shell => [],
            self::Ruby => [['=begin', '=end']],
            self::Html, self::Vue, self::Svelte => [['<!--', '-->']],
            self::Blade => [['{{--', '--}}'], ['<!--', '-->']],
            default => [['/*', '*/']],
        };
    }

    /**
     * @return list<string>
     */
    public function quotes(): array
    {
        return match ($this) {
            self::JavaScript, self::TypeScript, self::Go, self::Kotlin, self::Swift => ['"', "'", '`'],
            default => ['"', "'"],
        };
    }
}
