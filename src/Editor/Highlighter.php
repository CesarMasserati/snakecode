<?php

declare(strict_types=1);

namespace SnakeCode\Editor;

/**
 * Syntax highlighting sem dependências.
 *
 * - PHP: tokenizer nativo (token_get_all).
 * - Demais linguagens: lexer genérico (comentários, strings, números, palavras-chave,
 *   chamadas e tipos), com suporte a marcação (HTML, Blade, Vue, Svelte) e às
 *   seções <script>/<style> dentro dela.
 */
final class Highlighter
{
    public const PLAIN = 252;
    public const KEYWORD = 75;
    public const CONTROL = 176;
    public const VARIABLE = 117;
    public const STRING = 173;
    public const NUMBER = 151;
    public const COMMENT = 71;
    public const TYPE = 79;
    public const FUNCTION = 187;
    public const PUNCTUATION = 250;

    private const CONTROL_WORDS = [
        'if', 'else', 'elif', 'elseif', 'return', 'foreach', 'for', 'while', 'do', 'match', 'switch', 'case',
        'default', 'break', 'continue', 'throw', 'try', 'catch', 'finally', 'except', 'raise', 'yield',
        'await', 'new', 'use', 'import', 'export', 'from', 'with', 'as', 'namespace', 'require',
        'require_once', 'include', 'include_once', 'goto', 'when', 'unless', 'until', 'pass', 'defer', 'go',
    ];

    private const LITERALS = ['true', 'false', 'null', 'nil', 'none', 'undefined', 'this', 'self', 'super'];

    private const CODE_KEYWORDS = [
        'abstract', 'class', 'const', 'let', 'var', 'function', 'def', 'func', 'fn', 'fun', 'interface',
        'trait', 'enum', 'struct', 'impl', 'type', 'extends', 'implements', 'public', 'private', 'protected',
        'internal', 'static', 'final', 'readonly', 'async', 'void', 'int', 'float', 'double', 'bool',
        'boolean', 'string', 'char', 'long', 'short', 'byte', 'package', 'module', 'using', 'val', 'mut',
        'pub', 'crate', 'override', 'virtual', 'sealed', 'record', 'object', 'typeof', 'instanceof',
        'delete', 'in', 'of', 'is', 'not', 'and', 'or', 'lambda', 'declare', 'keyof', 'satisfies',
    ];

    private const SQL_KEYWORDS = [
        'select', 'insert', 'update', 'delete', 'from', 'where', 'join', 'left', 'right', 'inner', 'outer',
        'full', 'on', 'group', 'by', 'order', 'having', 'limit', 'offset', 'into', 'values', 'set', 'create',
        'table', 'alter', 'drop', 'index', 'primary', 'key', 'foreign', 'references', 'not', 'and', 'or',
        'is', 'in', 'distinct', 'union', 'all', 'exists', 'between', 'like', 'then', 'end', 'begin',
        'commit', 'rollback', 'unique', 'constraint', 'view', 'asc', 'desc',
    ];

    private const SHELL_KEYWORDS = [
        'fi', 'then', 'done', 'esac', 'in', 'function', 'local', 'export', 'readonly', 'source', 'echo',
        'exit', 'set', 'unset', 'shift',
    ];

    private const DECLARATIONS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    private const INSIGNIFICANT = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    public function highlight(string $code, Language $language = Language::Php): SourceFile
    {
        $code = str_replace(["\r\n", "\r"], "\n", mb_scrub($code, 'UTF-8'));
        $out = new LineBuffer();

        if ($language === Language::Php) {
            [$classes, $functions] = $this->tokenizePhp($code, $out);

            return new SourceFile($out->lines(), $classes, $functions, $language->label());
        }

        $this->lexGeneric(mb_str_split($code), $language, $out);
        $lines = $out->lines();
        [$classes, $functions] = self::scanSymbols($lines);

        return new SourceFile($lines, $classes, $functions, $language->label());
    }

    // ---------------------------------------------------------------- PHP

    /**
     * @return array{0: array<int, string>, 1: array<int, string>} classes e funções por linha
     */
    private function tokenizePhp(string $code, LineBuffer $out): array
    {
        $tokens = token_get_all($code);
        $classes = [];
        $functions = [];
        /** @var 'class'|'function'|null $expecting próximo identificador é o nome de um símbolo declarado */
        $expecting = null;

        foreach ($tokens as $i => $token) {
            [$id, $text] = is_array($token) ? [$token[0], $token[1]] : [null, $token];

            if ($id === T_STRING && $expecting !== null) {
                if ($expecting === 'class') {
                    $classes[$out->lineNumber()] = $text;
                } else {
                    $functions[$out->lineNumber()] = $text;
                }
                $expecting = null;
            } elseif (in_array($id, self::DECLARATIONS, true)) {
                // "Foo::class" não é declaração.
                $expecting = $this->previousSignificant($tokens, $i) === T_DOUBLE_COLON ? null : 'class';
            } elseif ($id === T_FUNCTION) {
                $expecting = 'function';
            } elseif ($id !== T_WHITESPACE && $text !== '&') {
                $expecting = null;
            }

            $out->append($text, $this->phpColor($id, $text, $tokens, $i));
        }

        return [$classes, $functions];
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function phpColor(?int $id, string $text, array $tokens, int $i): int
    {
        if ($id === null) {
            return ($text === '"' || $text === '`') ? self::STRING : self::PUNCTUATION;
        }

        return match (true) {
            $id === T_VARIABLE => self::VARIABLE,
            $id === T_COMMENT, $id === T_DOC_COMMENT => self::COMMENT,
            $id === T_CONSTANT_ENCAPSED_STRING, $id === T_ENCAPSED_AND_WHITESPACE,
            $id === T_START_HEREDOC, $id === T_END_HEREDOC => self::STRING,
            $id === T_LNUMBER, $id === T_DNUMBER => self::NUMBER,
            $id === T_NAME_QUALIFIED, $id === T_NAME_FULLY_QUALIFIED, $id === T_NAME_RELATIVE => self::TYPE,
            $id === T_OPEN_TAG, $id === T_OPEN_TAG_WITH_ECHO, $id === T_CLOSE_TAG => self::KEYWORD,
            $id === T_STRING => $this->phpIdentifierColor($text, $tokens, $i),
            $id === T_WHITESPACE, $id === T_INLINE_HTML => self::PLAIN,
            preg_match('/^[a-z_]+$/i', $text) === 1 => in_array(strtolower($text), self::CONTROL_WORDS, true)
                ? self::CONTROL
                : self::KEYWORD,
            default => self::PUNCTUATION,
        };
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function phpIdentifierColor(string $text, array $tokens, int $i): int
    {
        if (in_array(strtolower($text), self::LITERALS, true)) {
            return self::KEYWORD;
        }

        $previous = $this->previousSignificant($tokens, $i);
        $isCall = $this->nextSignificant($tokens, $i) === '(';

        if ($previous === T_OBJECT_OPERATOR || $previous === T_NULLSAFE_OBJECT_OPERATOR) {
            return $isCall ? self::FUNCTION : self::VARIABLE;
        }
        if ($isCall && $previous !== T_NEW) {
            return self::FUNCTION;
        }
        if (preg_match('/^[A-Z][A-Z0-9_]+$/', $text) === 1) {
            return self::KEYWORD;
        }

        return ctype_upper($text[0]) ? self::TYPE : self::PLAIN;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function nextSignificant(array $tokens, int $i): int|string|null
    {
        for ($j = $i + 1, $count = count($tokens); $j < $count; $j++) {
            if (!is_array($tokens[$j])) {
                return $tokens[$j];
            }
            if (!in_array($tokens[$j][0], self::INSIGNIFICANT, true)) {
                return $tokens[$j][0];
            }
        }

        return null;
    }

    /**
     * @param list<array{0: int, 1: string, 2: int}|string> $tokens
     */
    private function previousSignificant(array $tokens, int $i): int|string|null
    {
        for ($j = $i - 1; $j >= 0; $j--) {
            if (!is_array($tokens[$j])) {
                return $tokens[$j];
            }
            if (!in_array($tokens[$j][0], self::INSIGNIFICANT, true)) {
                return $tokens[$j][0];
            }
        }

        return null;
    }

    // ------------------------------------------------------- Lexer genérico

    /**
     * @param list<string> $chars
     */
    private function lexGeneric(array $chars, Language $language, LineBuffer $out): void
    {
        $count = count($chars);
        $markup = $language->isMarkup();
        /** Regras de código ativas; null = texto de um arquivo de marcação. */
        $rules = $markup ? null : $language;
        $inTag = false;
        $closingTag = false;
        $expectTagName = false;
        $tagName = '';
        $i = 0;

        while ($i < $count) {
            $char = $chars[$i];

            foreach (($rules ?? $language)->blockComments() as [$open, $close]) {
                if (self::matchesAt($chars, $i, $open)) {
                    $end = self::indexAfter($chars, $i + strlen($open), $close);
                    $out->append(self::slice($chars, $i, $end), self::COMMENT);
                    $i = $end;
                    continue 2;
                }
            }

            if ($rules !== null) {
                foreach ($rules->lineComments() as $open) {
                    if (self::matchesAt($chars, $i, $open)) {
                        $end = self::indexOf($chars, $i, "\n");
                        $out->append(self::slice($chars, $i, $end), self::COMMENT);
                        $i = $end;
                        continue 2;
                    }
                }
                if ($markup && (self::matchesAt($chars, $i, '</script', true) || self::matchesAt($chars, $i, '</style', true))) {
                    $rules = null; // fim da seção de código: o "</" é tratado como tag logo abaixo
                }
            }

            if (($rules !== null || $inTag) && in_array($char, ($rules ?? $language)->quotes(), true)) {
                $end = self::stringEnd($chars, $i, $char);
                $out->append(self::slice($chars, $i, $end), self::STRING);
                $i = $end;
                continue;
            }

            if ($rules === null && !$inTag && $char === '<' && preg_match('/^[A-Za-z\/]$/', $chars[$i + 1] ?? '') === 1) {
                $closingTag = $chars[$i + 1] === '/';
                $inTag = true;
                $expectTagName = true;
                $out->append($closingTag ? '</' : '<', self::PUNCTUATION);
                $i += $closingTag ? 2 : 1;
                continue;
            }

            if ($inTag && $char === '>') {
                $inTag = false;
                $out->append('>', self::PUNCTUATION);
                $i++;
                if (!$closingTag) {
                    $rules = match ($tagName) {
                        'script' => Language::JavaScript,
                        'style' => Language::Css,
                        default => null,
                    };
                }
                $tagName = '';
                continue;
            }

            if (ctype_digit($char)) {
                $end = $i + 1;
                while ($end < $count && preg_match('/^[0-9A-Fa-fxX._]$/', $chars[$end]) === 1) {
                    $end++;
                }
                $out->append(self::slice($chars, $i, $end), self::NUMBER);
                $i = $end;
                continue;
            }

            if (preg_match('/^[A-Za-z_$@]$/', $char) === 1) {
                $pattern = match (true) {
                    $inTag => '/^[\w\-:.@$]$/',
                    $rules === Language::Css, $rules === Language::Scss => '/^[\w\-]$/',
                    default => '/^[\w$]$/',
                };
                $end = $i + 1;
                while ($end < $count && preg_match($pattern, $chars[$end]) === 1) {
                    $end++;
                }
                $word = self::slice($chars, $i, $end);
                $out->append($word, $this->wordColor($word, $chars, $end, $rules, $inTag, $expectTagName));
                if ($expectTagName) {
                    $tagName = strtolower($word);
                    $expectTagName = false;
                }
                $i = $end;
                continue;
            }

            $expectTagName = false;
            $out->append($char, ($char === ' ' || $char === "\n" || $char === "\t") ? self::PLAIN : self::PUNCTUATION);
            $i++;
        }
    }

    /**
     * @param list<string> $chars
     */
    private function wordColor(string $word, array $chars, int $end, ?Language $rules, bool $inTag, bool $isTagName): int
    {
        if ($isTagName) {
            return self::KEYWORD;
        }
        if ($inTag) {
            return self::VARIABLE; // atributos
        }
        if ($word[0] === '$') {
            return self::VARIABLE;
        }
        if ($word[0] === '@') {
            return self::CONTROL; // diretivas Blade, decorators, @media
        }
        if ($rules === null) {
            return self::PLAIN; // texto do documento
        }

        $next = self::nextNonBlank($chars, $end);
        if (($rules === Language::Css || $rules === Language::Scss) && $next === ':') {
            return self::VARIABLE; // propriedade CSS
        }

        $lower = strtolower($word);
        if (in_array($lower, self::LITERALS, true)) {
            return self::KEYWORD;
        }
        if (in_array($lower, self::CONTROL_WORDS, true)) {
            return self::CONTROL;
        }
        if (in_array($lower, self::keywordsFor($rules), true)) {
            return self::KEYWORD;
        }
        if ($next === '(') {
            return self::FUNCTION;
        }
        if (preg_match('/^[A-Z][A-Z0-9_]+$/', $word) === 1) {
            return self::KEYWORD;
        }

        return ctype_upper($word[0]) ? self::TYPE : self::PLAIN;
    }

    /**
     * @return list<string>
     */
    private static function keywordsFor(Language $language): array
    {
        return match ($language) {
            Language::Sql => self::SQL_KEYWORDS,
            Language::Shell => self::SHELL_KEYWORDS,
            default => self::CODE_KEYWORDS,
        };
    }

    /**
     * Símbolos para o breadcrumb, detectados por linha.
     *
     * @param list<array{0: list<string>, 1: list<int>}> $lines
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private static function scanSymbols(array $lines): array
    {
        $classes = [];
        $functions = [];
        foreach ($lines as $index => [$chars]) {
            $text = implode('', $chars);
            if (preg_match('/\b(?:class|interface|trait|enum|struct|impl|record)\s+([A-Za-z_]\w*)/', $text, $match) === 1) {
                $classes[$index + 1] = $match[1];
            } elseif (preg_match('/\b(?:function|def|func|fn|fun)\s+(?:\([^)]*\)\s*)?\*?([A-Za-z_$][\w$]*)/', $text, $match) === 1) {
                $functions[$index + 1] = $match[1];
            }
        }

        return [$classes, $functions];
    }

    /**
     * Compara um trecho ASCII a partir de $i (sem alocar: é chamado a cada caractere).
     *
     * @param list<string> $chars
     */
    private static function matchesAt(array $chars, int $i, string $needle, bool $caseInsensitive = false): bool
    {
        for ($k = 0, $length = strlen($needle); $k < $length; $k++) {
            $actual = $chars[$i + $k] ?? null;
            if ($actual === null) {
                return false;
            }
            if ($caseInsensitive) {
                $actual = strtolower($actual);
            }
            if ($actual !== $needle[$k]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $chars
     *
     * @return int índice logo após a ocorrência de $needle (ou o fim do arquivo)
     */
    private static function indexAfter(array $chars, int $from, string $needle): int
    {
        for ($j = $from, $count = count($chars); $j < $count; $j++) {
            if (self::matchesAt($chars, $j, $needle)) {
                return $j + strlen($needle);
            }
        }

        return count($chars);
    }

    /**
     * @param list<string> $chars
     */
    private static function indexOf(array $chars, int $from, string $char): int
    {
        for ($j = $from, $count = count($chars); $j < $count; $j++) {
            if ($chars[$j] === $char) {
                return $j;
            }
        }

        return count($chars);
    }

    /**
     * @param list<string> $chars
     */
    private static function stringEnd(array $chars, int $i, string $quote): int
    {
        $triple = $quote . $quote . $quote;
        if (self::matchesAt($chars, $i, $triple)) {
            return self::indexAfter($chars, $i + 3, $triple);
        }

        for ($j = $i + 1, $count = count($chars); $j < $count; $j++) {
            $char = $chars[$j];
            if ($char === '\\') {
                $j++;
                continue;
            }
            if ($char === $quote) {
                return $j + 1;
            }
            if ($char === "\n" && $quote !== '`') {
                return $j; // string não terminada: não contamina as linhas seguintes
            }
        }

        return count($chars);
    }

    /**
     * @param list<string> $chars
     */
    private static function nextNonBlank(array $chars, int $from): string
    {
        for ($j = $from, $count = count($chars); $j < $count; $j++) {
            if ($chars[$j] !== ' ' && $chars[$j] !== "\t") {
                return $chars[$j];
            }
        }

        return '';
    }

    /**
     * @param list<string> $chars
     */
    private static function slice(array $chars, int $from, int $to): string
    {
        return implode('', array_slice($chars, $from, $to - $from));
    }
}
