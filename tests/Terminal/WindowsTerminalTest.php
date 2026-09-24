<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Terminal;

use PHPUnit\Framework\TestCase;
use SnakeCode\Terminal\Command;
use SnakeCode\Terminal\Input;
use SnakeCode\Terminal\WindowsTerminal;

/**
 * A tradução de teclas do console do Windows é pura e roda em qualquer SO.
 */
final class WindowsTerminalTest extends TestCase
{
    private const VK_UP = 0x26;
    private const VK_DOWN = 0x28;
    private const VK_LEFT = 0x25;
    private const VK_RIGHT = 0x27;
    private const VK_SHIFT = 0x10;

    public function testArrowsBecomeVtSequences(): void
    {
        self::assertSame("\e[A", WindowsTerminal::translateKey(self::VK_UP, 0));
        self::assertSame("\e[B", WindowsTerminal::translateKey(self::VK_DOWN, 0));
        self::assertSame("\e[C", WindowsTerminal::translateKey(self::VK_RIGHT, 0));
        self::assertSame("\e[D", WindowsTerminal::translateKey(self::VK_LEFT, 0));
    }

    public function testCharactersAndControlKeys(): void
    {
        self::assertSame('w', WindowsTerminal::translateKey(0x57, ord('w')));
        self::assertSame("\e", WindowsTerminal::translateKey(0x1B, 0x1B));
        self::assertSame("\r", WindowsTerminal::translateKey(0x0D, 0x0D));
        self::assertSame("\x03", WindowsTerminal::translateKey(0x43, 0x03));
        self::assertSame('ç', WindowsTerminal::translateKey(0xBA, 0xE7));
        self::assertSame('', WindowsTerminal::translateKey(self::VK_SHIFT, 0));
        self::assertSame('', WindowsTerminal::translateKey(0, 0xD83D));
    }

    public function testTranslatedKeysDriveTheSameCommandsAsLinux(): void
    {
        $bytes = WindowsTerminal::translateKey(self::VK_UP, 0)
            . WindowsTerminal::translateKey(0x1B, 0x1B)
            . WindowsTerminal::translateKey(0x43, 0x03);

        self::assertSame([Command::Up, Command::Panic, Command::Quit], Input::parse($bytes));
    }
}
