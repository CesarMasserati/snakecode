<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Editor;

use PHPUnit\Framework\TestCase;
use SnakeCode\Editor\Highlighter;

final class HighlighterTest extends TestCase
{
    public function testSplitsLinesAndColorsVariables(): void
    {
        $file = (new Highlighter())->highlight("<?php\n\$total = 1;\n");

        self::assertSame(2, $file->lineCount());
        [$chars, $colors] = $file->lines[1];
        self::assertSame('$total = 1;', implode('', $chars));
        self::assertSame(Highlighter::VARIABLE, $colors[0]);
        self::assertSame(Highlighter::NUMBER, $colors[9]);
    }

    public function testExpandsTabs(): void
    {
        $file = (new Highlighter())->highlight("<?php\n\tfoo();\n");

        self::assertSame('    foo();', implode('', $file->lines[1][0]));
        self::assertSame(Highlighter::FUNCTION, $file->lines[1][1][4]);
    }

    public function testTracksDeclaredSymbols(): void
    {
        $code = "<?php\nfinal class Foo\n{\n    public function bar(): string\n    {\n        return Foo::class;\n    }\n}\n";
        $file = (new Highlighter())->highlight($code);

        self::assertSame('Foo', $file->symbolAt(2));
        self::assertSame('Foo › bar()', $file->symbolAt(6));
        self::assertSame([2 => 'Foo'], $file->classes);
    }

    public function testEveryCharacterOccupiesOneColumn(): void
    {
        $file = (new Highlighter())->highlight("<?php\n// 日本 ok\x01\n");

        self::assertSame('// ?? ok ', implode('', $file->lines[1][0]));
    }
}
