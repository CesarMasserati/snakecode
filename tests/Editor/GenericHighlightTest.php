<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Editor;

use PHPUnit\Framework\TestCase;
use SnakeCode\Editor\Highlighter;
use SnakeCode\Editor\Language;
use SnakeCode\Editor\SourceFile;

final class GenericHighlightTest extends TestCase
{
    public function testLanguageWhitelist(): void
    {
        self::assertSame(Language::Blade, Language::fromPath('resources/views/home.blade.php'));
        self::assertSame(Language::Php, Language::fromPath('app/User.php'));
        self::assertSame(Language::TypeScript, Language::fromPath('src/App.tsx'));
        self::assertNull(Language::fromPath('logo.png'));
        self::assertNull(Language::fromPath('README.md'));
        self::assertNull(Language::fromPath('composer.lock'));
    }

    public function testJavaScript(): void
    {
        $file = (new Highlighter())->highlight("// nota\nconst total = sum('a', 2);\n", Language::JavaScript);

        self::assertSame('JavaScript', $file->language);
        self::assertSame(Highlighter::COMMENT, $this->colorOf($file, 0, '// nota'));
        self::assertSame(Highlighter::KEYWORD, $this->colorOf($file, 1, 'const'));
        self::assertSame(Highlighter::FUNCTION, $this->colorOf($file, 1, 'sum'));
        self::assertSame(Highlighter::STRING, $this->colorOf($file, 1, "'a'"));
        self::assertSame(Highlighter::NUMBER, $this->colorOf($file, 1, '2'));
    }

    public function testBladeMarkupAndDirectives(): void
    {
        $code = "{{-- menu --}}\n<div class=\"nav\">\n@if(\$user)\n{{ \$user->name }}\n@endif\n</div>\n";
        $file = (new Highlighter())->highlight($code, Language::Blade);

        self::assertSame(Highlighter::COMMENT, $this->colorOf($file, 0, '{{-- menu --}}'));
        self::assertSame(Highlighter::KEYWORD, $this->colorOf($file, 1, 'div'));
        self::assertSame(Highlighter::VARIABLE, $this->colorOf($file, 1, 'class'));
        self::assertSame(Highlighter::STRING, $this->colorOf($file, 1, '"nav"'));
        self::assertSame(Highlighter::CONTROL, $this->colorOf($file, 2, '@if'));
        self::assertSame(Highlighter::VARIABLE, $this->colorOf($file, 3, '$user'));
    }

    public function testVueScriptSectionUsesCodeRules(): void
    {
        $code = "<template><p>it's fine</p></template>\n<script setup>\nconst msg = 'oi'\n</script>\n";
        $file = (new Highlighter())->highlight($code, Language::Vue);

        // Apóstrofo no texto do template não abre string (senão "fine" ficaria colorido como string).
        self::assertSame(Highlighter::PLAIN, $this->colorOf($file, 0, 'fine'));
        self::assertSame(Highlighter::KEYWORD, $this->colorOf($file, 2, 'const'));
        self::assertSame(Highlighter::STRING, $this->colorOf($file, 2, "'oi'"));
        self::assertSame(Highlighter::KEYWORD, $this->colorOf($file, 3, 'script'));
    }

    public function testPythonSymbolsForBreadcrumb(): void
    {
        $code = "class Report:\n    def build(self):\n        return 1\n";
        $file = (new Highlighter())->highlight($code, Language::Python);

        self::assertSame('Report › build()', $file->symbolAt(3));
    }

    /**
     * Cor do primeiro caractere de $needle na linha (e garante que o trecho inteiro tem a mesma cor).
     */
    private function colorOf(SourceFile $file, int $line, string $needle): int
    {
        [$chars, $colors] = $file->lines[$line];
        $offset = mb_strpos(implode('', $chars), $needle);
        self::assertNotFalse($offset, sprintf('"%s" não encontrado na linha %d', $needle, $line));

        $slice = array_unique(array_slice($colors, $offset, mb_strlen($needle)));
        self::assertCount(1, $slice, sprintf('"%s" tem cores mistas', $needle));

        return $colors[$offset];
    }
}
