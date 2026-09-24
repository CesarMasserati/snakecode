<?php

declare(strict_types=1);

namespace SnakeCode\Terminal;

/**
 * Tradução das teclas (bytes/sequências de escape VT) em comandos do jogo.
 * A leitura em si é feita pelo Terminal de cada sistema operacional.
 */
final class Input
{
    public function __construct(
        private readonly Terminal $terminal,
    ) {
    }

    /**
     * Aguarda até $timeoutUs microssegundos por teclas.
     *
     * @return list<Command>
     */
    public function poll(int $timeoutUs): array
    {
        return self::parse($this->terminal->read($timeoutUs));
    }

    /** Retorna bytes sem traduzir, para os modos de edição e configuração. */
    public function readRaw(int $timeoutUs): string
    {
        return $this->terminal->read($timeoutUs);
    }

    /**
     * @return list<Command>
     */
    public static function parse(string $bytes): array
    {
        $commands = [];
        $length = strlen($bytes);
        $i = 0;

        while ($i < $length) {
            $byte = $bytes[$i];

            if ($byte === "\e") {
                $introducer = $bytes[$i + 1] ?? '';
                if ($introducer === '[' || $introducer === 'O') {
                    // CSI/SS3: pula parâmetros (ex.: "\e[1;5C") até o byte final.
                    $j = $i + 2;
                    while ($j < $length && !self::isFinalByte($bytes[$j])) {
                        $j++;
                    }
                    $command = match ($bytes[$j] ?? '') {
                        'A' => Command::Up,
                        'B' => Command::Down,
                        'C' => Command::Right,
                        'D' => Command::Left,
                        default => null,
                    };
                    if ($command !== null) {
                        $commands[] = $command;
                    }
                    $i = $j + 1;
                    continue;
                }

                $commands[] = Command::Panic;
                $i++;
                continue;
            }

            $command = match ($byte) {
                'w', 'W', 'k', 'K' => Command::Up,
                's', 'S', 'j', 'J' => Command::Down,
                'a', 'A', 'h', 'H' => Command::Left,
                'd', 'D', 'l', 'L' => Command::Right,
                'p', 'P', ' ' => Command::Pause,
                'v', 'V' => Command::Stealth,
                'm', 'M' => Command::Menu,
                'c', 'C' => Command::Settings,
                '`' => Command::Panic,
                'q', 'Q', "\x03" => Command::Quit,
                "\r", "\n" => Command::Confirm,
                default => null,
            };
            if ($command !== null) {
                $commands[] = $command;
            }
            $i++;
        }

        return $commands;
    }

    private static function isFinalByte(string $byte): bool
    {
        $code = ord($byte);

        return $code >= 0x40 && $code <= 0x7E;
    }
}
