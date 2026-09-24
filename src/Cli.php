<?php

declare(strict_types=1);

namespace SnakeCode;

use SnakeCode\Editor\Highlighter;
use SnakeCode\Editor\ProjectScanner;
use SnakeCode\Editor\SourceRepository;
use SnakeCode\Render\Renderer;
use SnakeCode\Render\Theme;
use SnakeCode\Storage\StateStore;
use SnakeCode\Terminal\Input;
use SnakeCode\Terminal\TerminalFactory;
use SnakeCode\Terminal\WindowsTerminal;

/**
 * Interpreta a linha de comando e monta o jogo.
 */
final class Cli
{
    private const DEFAULT_COLS = 100;
    private const DEFAULT_ROWS = 30;
    private const MAX_FILES = 500;

    private const OPTIONS = ['help', 'seed', 'stealth', 'src', 'files', 'render-once', 'no-menu'];

    private const USAGE = <<<'TXT'
Uso: snakecode [opções] [PASTA_DO_PROJETO]

  PASTA_DO_PROJETO              projeto cujo código vira o fundo do editor (padrão: o código do próprio jogo)
  --files=N                     quantos arquivos usar, os editados mais recentemente primeiro (padrão: 40)
  --stealth=subtle|medium|easy  nível de discrição da cobra (padrão: último usado)
  --no-menu                     pula o menu inicial e vai direto para o jogo
  --seed=N                      sorteio determinístico
  --render-once                 imprime um único frame e sai (sem TTY)
  -h, --help                    esta ajuda

Controles: setas/WASD/hjkl mover · p pausa · v discrição · Esc/` pânico · m menu · Enter reinicia · q sai

TXT;

    /**
     * @param list<string> $argv
     */
    public static function main(string $root, array $argv): int
    {
        [$options, $arguments] = self::parseArguments($argv);

        $unknown = array_diff(array_keys($options), self::OPTIONS);
        if ($unknown !== []) {
            return self::fail(sprintf('Opção desconhecida: --%s', reset($unknown)));
        }
        if (isset($options['help'])) {
            fwrite(STDOUT, self::USAGE);

            return 0;
        }
        foreach (['seed', 'stealth', 'src', 'files'] as $name) {
            if (isset($options[$name]) && !is_string($options[$name])) {
                return self::fail(sprintf('--%s precisa de um valor, por exemplo --%s=...', $name, $name));
            }
        }
        if (count($arguments) > 1) {
            return self::fail('Informe apenas uma pasta de projeto.');
        }

        $store = StateStore::default();
        $saved = $store->load();
        $settings = GameSettings::fromArray(is_array($saved['settings'] ?? null) ? $saved['settings'] : []);

        $profile = $options['stealth'] ?? null;
        if ($profile !== null && !in_array($profile, Theme::PROFILES, true)) {
            return self::fail(sprintf('Perfil de discrição inválido. Use: %s', implode(', ', Theme::PROFILES)));
        }
        $profile ??= in_array($saved['stealth'] ?? null, Theme::PROFILES, true) ? $saved['stealth'] : 'subtle';

        $seed = $options['seed'] ?? null;
        if ($seed !== null && filter_var($seed, FILTER_VALIDATE_INT) === false) {
            return self::fail('--seed precisa ser um número inteiro.');
        }

        $limit = $options['files'] ?? (string) $settings->fileCount;
        if (filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_FILES]]) === false) {
            return self::fail(sprintf('--files precisa ser um número entre 1 e %d.', self::MAX_FILES));
        }

        $explicitProject = $arguments[0] ?? $options['src'] ?? null;
        $project = $explicitProject ?? $settings->projectPath;
        if ($explicitProject === null && $project !== null && (!is_dir($project) || !is_readable($project))) {
            $settings->projectPath = null;
            $project = null;
        }
        if ($project !== null) {
            $base = realpath($project);
            if ($base === false || !is_dir($base) || !is_readable($base)) {
                return self::fail(sprintf('Pasta do projeto não encontrada ou sem permissão de leitura: %s', $project));
            }
            $base = str_replace('\\', '/', $base);
            $files = (new ProjectScanner())->scan($base, (int) $limit);
            if ($files === [] && $explicitProject !== null) {
                return self::fail(sprintf(
                    'Nenhum arquivo de código encontrado em %s (vendor/, node_modules/, mídia e documentos são ignorados).',
                    $base,
                ));
            }
        } else {
            $base = $root;
            $files = (new ProjectScanner())->scan($root . '/src', (int) $limit);
        }
        if ($files === [] && $explicitProject === null) {
            $settings->projectPath = null;
            $base = $root;
            $files = (new ProjectScanner())->scan($root . '/src', (int) $limit);
        }
        $settings->fileCount = (int) $limit;
        if ($explicitProject !== null) $settings->projectPath = $base;

        $terminal = TerminalFactory::create();

        $game = new Game(
            $terminal,
            new Input($terminal),
            new Renderer(),
            new SourceRepository($files, $base, new Highlighter()),
            $store,
            new Theme($profile, $settings->snakeColor),
            is_numeric($saved['best'] ?? null) ? (int) $saved['best'] : 0,
            $root,
            $seed === null ? null : (int) $seed,
            showMenu: !isset($options['no-menu']),
            settings: $settings,
        );

        if (isset($options['render-once'])) {
            fwrite(STDOUT, $game->snapshot(
                self::envSize('COLUMNS', self::DEFAULT_COLS),
                self::envSize('LINES', self::DEFAULT_ROWS),
            ));

            return 0;
        }

        if (!TerminalFactory::isInteractive()) {
            return self::fail('O snakecode precisa de um terminal interativo (TTY). Para testar sem TTY use --render-once.', 1);
        }
        if (TerminalFactory::isWindows() && ($reason = WindowsTerminal::unavailableReason()) !== null) {
            return self::fail($reason, 1);
        }

        return $game->run();
    }

    /**
     * Aceita "--nome=valor", "--flag", "-h" e argumentos posicionais em qualquer ordem.
     *
     * @param list<string> $argv
     *
     * @return array{0: array<string, string|true>, 1: list<string>}
     */
    private static function parseArguments(array $argv): array
    {
        $options = [];
        $arguments = [];

        foreach (array_slice($argv, 1) as $argument) {
            if ($argument === '-h') {
                $options['help'] = true;
            } elseif (str_starts_with($argument, '--')) {
                [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, true);
                $options[$name] = $value;
            } else {
                $arguments[] = $argument;
            }
        }

        return [$options, $arguments];
    }

    private static function fail(string $message, int $code = 2): int
    {
        fwrite(STDERR, $message . PHP_EOL);

        return $code;
    }

    private static function envSize(string $name, int $default): int
    {
        $value = getenv($name);

        return is_string($value) && ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
