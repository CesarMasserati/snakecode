<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Terminal;

use PHPUnit\Framework\TestCase;
use SnakeCode\Terminal\Command;
use SnakeCode\Terminal\Input;

final class InputTest extends TestCase
{
    public function testArrowKeys(): void
    {
        self::assertSame(
            [Command::Up, Command::Down, Command::Right, Command::Left],
            Input::parse("\e[A\e[B\e[C\e[D"),
        );
    }

    public function testApplicationModeAndModifiedArrows(): void
    {
        self::assertSame([Command::Up, Command::Right], Input::parse("\eOA\e[1;5C"));
    }

    public function testLetterKeys(): void
    {
        self::assertSame(
            [Command::Up, Command::Left, Command::Down, Command::Right, Command::Left, Command::Down, Command::Up, Command::Right],
            Input::parse('wasdhjkl'),
        );
    }

    public function testControlKeys(): void
    {
        self::assertSame([Command::Panic], Input::parse("\e"));
        self::assertSame([Command::Panic], Input::parse('`'));
        self::assertSame([Command::Quit, Command::Quit], Input::parse("q\x03"));
        self::assertSame([Command::Confirm], Input::parse("\r"));
        self::assertSame([Command::Pause, Command::Pause, Command::Stealth], Input::parse('p v'));
        self::assertSame([Command::Menu, Command::Menu], Input::parse('mM'));
        self::assertSame([], Input::parse('xz'));
    }
}
