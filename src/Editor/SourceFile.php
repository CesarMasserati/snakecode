<?php

declare(strict_types=1);

namespace SnakeCode\Editor;

/**
 * Arquivo já colorido: caracteres e cores por linha, mais o mapa de símbolos declarados.
 */
final readonly class SourceFile
{
    /**
     * @param list<array{0: list<string>, 1: list<int>}> $lines     caracteres e cores (256) por linha
     * @param array<int, string>                         $classes   linha (1-based) => classe/interface/enum
     * @param array<int, string>                         $functions linha (1-based) => função/método
     * @param string                                     $language  nome exibido na statusbar
     */
    public function __construct(
        public array $lines,
        public array $classes,
        public array $functions,
        public string $language = 'PHP',
    ) {
    }

    public function lineCount(): int
    {
        return count($this->lines);
    }

    /**
     * Símbolo que "contém" a linha, no formato do breadcrumb: "Classe › metodo()".
     */
    public function symbolAt(int $line): ?string
    {
        $class = self::lastDeclaredUntil($this->classes, $line);
        $function = self::lastDeclaredUntil($this->functions, $line);

        return match (true) {
            $class !== null && $function !== null => $class . ' › ' . $function . '()',
            $class !== null => $class,
            $function !== null => $function . '()',
            default => null,
        };
    }

    /**
     * @param array<int, string> $symbols ordenado por linha
     */
    private static function lastDeclaredUntil(array $symbols, int $line): ?string
    {
        $found = null;
        foreach ($symbols as $declaredAt => $name) {
            if ($declaredAt > $line) {
                break;
            }
            $found = $name;
        }

        return $found;
    }
}
