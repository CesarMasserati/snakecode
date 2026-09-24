<?php

/*
 * Inicialização do SnakeCode, compartilhada pelo bin/snakecode e pelo snakecode.phar.
 *
 * Usa apenas sintaxe compatível com PHPs antigos, para que um binário anterior
 * ao 8.3 mostre uma mensagem clara em vez de um erro de parse.
 */

if (PHP_VERSION_ID < 80300) {
    fwrite(STDERR, 'snakecode requer PHP >= 8.3 (atual: ' . PHP_VERSION . ").\n");
    exit(1);
}

foreach (array('mbstring', 'tokenizer') as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "snakecode requer a extensão PHP '{$extension}' (habilite no php.ini).\n");
        exit(1);
    }
}

// Separador "/" em todos os caminhos (o PHP no Windows aceita os dois).
$root = str_replace('\\', '/', __DIR__);

spl_autoload_register(function ($class) use ($root) {
    $prefix = 'SnakeCode\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = $root . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

try {
    exit(\SnakeCode\Cli::main($root, isset($argv) ? $argv : array()));
} catch (\Throwable $e) {
    fwrite(STDERR, 'snakecode: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
