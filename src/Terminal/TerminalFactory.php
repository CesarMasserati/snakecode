<?php

declare(strict_types=1);

namespace SnakeCode\Terminal;

/**
 * Escolhe a implementação de terminal do sistema operacional atual.
 */
final class TerminalFactory
{
    public static function create(): Terminal
    {
        return self::isWindows() ? new WindowsTerminal() : new PosixTerminal();
    }

    public static function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public static function isInteractive(): bool
    {
        return stream_isatty(STDIN) && stream_isatty(STDOUT);
    }
}
