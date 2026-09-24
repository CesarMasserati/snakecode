<?php

declare(strict_types=1);

namespace SnakeCode\Render;

use Throwable;

/**
 * Formata a exceção real da colisão (com o stack trace verdadeiro) como a saída
 * de um terminal integrado de IDE.
 */
final class CrashPanel
{
    /**
     * @return list<array{0: string, 1: int}> linhas [texto, cor]
     */
    public static function lines(Throwable $error, string $root, int $score, int $best, int $height): array
    {
        $root = str_replace('\\', '/', $root);
        $relative = static function (string $path) use ($root): string {
            $path = str_replace('\\', '/', $path);

            return str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path;
        };

        $head = [
            ['$ snakecode', Theme::PANEL_PROMPT],
            [sprintf(
                'PHP Fatal error:  Uncaught %s: %s in %s:%d',
                $error::class,
                $error->getMessage(),
                $relative($error->getFile()),
                $error->getLine(),
            ), Theme::PANEL_ERROR],
            ['Stack trace:', Theme::FG],
        ];

        $trace = [];
        foreach ($error->getTrace() as $i => $frame) {
            $location = isset($frame['file'])
                ? sprintf('%s(%d)', $relative($frame['file']), $frame['line'] ?? 0)
                : '[internal function]';
            $trace[] = [sprintf(
                '#%d %s: %s%s%s()',
                $i,
                $location,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'],
            ), Theme::PANEL_TRACE];
        }
        $trace[] = [sprintf('#%d {main}', count($trace)), Theme::PANEL_TRACE];

        $tail = [
            [sprintf('  thrown in %s on line %d', $relative($error->getFile()), $error->getLine()), Theme::PANEL_ERROR],
            [sprintf(
                ' *  The terminal process "snakecode" terminated with exit code: %d (peak: %d).',
                $score,
                $best,
            ), Theme::FG],
            [' *  Terminal will be reused by tasks, press Enter to re-run.', Theme::FG],
        ];

        $room = max(0, $height - count($head) - count($tail));
        $lines = [...$head, ...array_slice($trace, 0, $room), ...$tail];

        // Painel muito baixo: preserva o final (instrução de "re-run").
        return count($lines) > $height ? array_slice($lines, -$height) : $lines;
    }
}
