<?php

declare(strict_types=1);

/**
 * Gera os pacotes de distribuição do SnakeCode em dist/:
 *
 *   snakecode.phar                    qualquer SO com PHP >= 8.3 (mbstring; FFI no Windows)
 *   snakecode-<v>-linux.tar.gz        phar + lançador + install.sh/uninstall.sh
 *   snakecode-<v>-windows-x64.zip     phar + PHP portátil oficial (SHA-256 verificado)
 *                                     + snakecode.cmd + instalador PowerShell
 *
 * Uso: php8.3 -d phar.readonly=0 build/package.php [--no-windows]
 */

const VERSION = '1.0.0';
const PHP_BRANCH = '8.3';
const PHP_BUILD = 'nts-vs16-x64';
const RELEASES_URL = 'https://downloads.php.net/~windows/releases/releases.json';
const DOWNLOAD_URL = 'https://downloads.php.net/~windows/releases/';
/** Extensões necessárias; as que não vierem embutidas no php8.dll são copiadas e habilitadas. */
const WINDOWS_EXTENSIONS = ['mbstring', 'ffi', 'tokenizer', 'phar'];

$root = dirname(__DIR__);

if (ini_get('phar.readonly')) {
    fail('Rode com: php8.3 -d phar.readonly=0 build/package.php');
}

$dist = "{$root}/dist";
$work = "{$root}/build/work";
$cache = "{$root}/build/cache";
removeTree($work);
foreach ([$dist, $work, $cache] as $dir) {
    ensureDir($dir);
}

try {
    $phar = buildPhar($root, $dist);
    report($phar);
    report(buildLinux($root, $dist, $work, $phar));
    if (!in_array('--no-windows', $argv, true)) {
        report(buildWindows($root, $dist, $work, $cache, $phar));
    }
} finally {
    removeTree($work);
}

// ------------------------------------------------------------------ phar

function buildPhar(string $root, string $dist): string
{
    $path = "{$dist}/snakecode.phar";
    @unlink($path);

    $phar = new Phar($path, 0, 'snakecode.phar');
    $phar->startBuffering();
    $phar->addFile("{$root}/bootstrap.php", 'bootstrap.php');
    foreach (filesUnder("{$root}/src") as $file) {
        $phar->addFile($file, 'src/' . substr($file, strlen("{$root}/src/")));
    }
    $phar->addFromString('VERSION', VERSION . "\n");
    $phar->setStub(
        "#!/usr/bin/env php\n<?php\n"
        . "Phar::mapPhar('snakecode.phar');\n"
        . "require 'phar://snakecode.phar/bootstrap.php';\n"
        . "__HALT_COMPILER();\n",
    );
    $phar->stopBuffering();
    unset($phar);
    chmod($path, 0755);

    return $path;
}

// ----------------------------------------------------------------- Linux

function buildLinux(string $root, string $dist, string $work, string $phar): string
{
    $name = 'snakecode-' . VERSION . '-linux';
    $stage = "{$work}/{$name}";
    ensureDir($stage);

    copy($phar, "{$stage}/snakecode.phar");
    foreach (['snakecode', 'install.sh', 'uninstall.sh', 'LEIA-ME.txt'] as $file) {
        file_put_contents("{$stage}/{$file}", render("{$root}/packaging/linux/{$file}"));
    }
    foreach (['snakecode', 'install.sh', 'uninstall.sh', 'snakecode.phar'] as $file) {
        chmod("{$stage}/{$file}", 0755);
    }

    $tar = "{$dist}/{$name}.tar";
    foreach ([$tar, "{$tar}.gz"] as $old) {
        @unlink($old);
    }
    $archive = new PharData($tar);
    $archive->buildFromDirectory($work);
    $archive->compress(Phar::GZ);
    unset($archive);
    Phar::unlinkArchive($tar);

    return "{$tar}.gz";
}

// --------------------------------------------------------------- Windows

function buildWindows(string $root, string $dist, string $work, string $cache, string $phar): string
{
    if (!class_exists(ZipArchive::class)) {
        fail('A extensão zip é necessária para gerar o pacote Windows (ou use --no-windows).');
    }

    $release = phpRelease();
    $zipPath = "{$cache}/{$release['path']}";
    if (!is_file($zipPath) || hash_file('sha256', $zipPath) !== $release['sha256']) {
        echo "Baixando {$release['path']} ...\n";
        download(DOWNLOAD_URL . $release['path'], $zipPath);
        if (hash_file('sha256', $zipPath) !== $release['sha256']) {
            @unlink($zipPath);
            fail('SHA-256 do PHP para Windows não confere com o publicado; download descartado.');
        }
    }

    $name = 'snakecode-' . VERSION . '-windows-x64';
    $stage = "{$work}/{$name}/SnakeCode";
    ensureDir("{$stage}/php/ext");

    $source = new ZipArchive();
    if ($source->open($zipPath) !== true) {
        fail("Não foi possível abrir {$zipPath}");
    }

    // Runtime mínimo: php.exe, extensões que não são embutidas e as DLLs que eles importam.
    $wanted = ['php.exe', 'license.txt'];
    $extensions = [];
    foreach (WINDOWS_EXTENSIONS as $extension) {
        if ($source->locateName("ext/php_{$extension}.dll") !== false) {
            $wanted[] = "ext/php_{$extension}.dll";
            $extensions[] = $extension;
        }
    }
    foreach (dependencies($source, $wanted) as $entry) {
        $data = $source->getFromName($entry);
        if ($data === false) {
            fail("Arquivo ausente no pacote do PHP: {$entry}");
        }
        file_put_contents("{$stage}/php/{$entry}", $data);
    }
    $source->close();

    $iniLines = array_map(static fn (string $ext): string => "extension = {$ext}", $extensions);
    file_put_contents("{$stage}/php/php.ini", windowsText(str_replace(
        '{{EXTENSIONS}}',
        $iniLines === [] ? '; (todas as extensões necessárias já são embutidas)' : implode("\n", $iniLines),
        render("{$root}/packaging/windows/php.ini"),
    )));

    copy($phar, "{$stage}/snakecode.phar");
    foreach (['snakecode.cmd', 'install.cmd', 'uninstall.cmd', 'install.ps1', 'uninstall.ps1', 'LEIA-ME.txt'] as $file) {
        $text = windowsText(render("{$root}/packaging/windows/{$file}", ['PHP_VERSION' => $release['version']]));
        // PowerShell 5.1 e o Bloco de Notas só reconhecem UTF-8 com BOM.
        $bom = str_ends_with($file, '.cmd') ? '' : "\xEF\xBB\xBF";
        file_put_contents("{$stage}/{$file}", $bom . $text);
    }

    $zip = "{$dist}/{$name}.zip";
    @unlink($zip);
    $archive = new ZipArchive();
    if ($archive->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail("Não foi possível criar {$zip}");
    }
    $base = "{$work}/{$name}/";
    foreach (filesUnder("{$work}/{$name}", null) as $file) {
        $archive->addFile($file, substr($file, strlen($base)));
    }
    $archive->close();

    return $zip;
}

/**
 * @return array{version: string, path: string, sha256: string}
 */
function phpRelease(): array
{
    $json = @file_get_contents(RELEASES_URL, false, httpContext());
    $releases = is_string($json) ? json_decode($json, true) : null;
    $zip = $releases[PHP_BRANCH][PHP_BUILD]['zip'] ?? null;
    if (!is_array($zip) || !isset($zip['path'], $zip['sha256'])) {
        fail('Não foi possível obter a lista de versões do PHP para Windows em ' . RELEASES_URL);
    }

    return ['version' => (string) $releases[PHP_BRANCH]['version'], 'path' => $zip['path'], 'sha256' => strtolower($zip['sha256'])];
}

/**
 * Fecha a lista de arquivos com as DLLs importadas (transitivamente) que existem no pacote oficial.
 * DLLs do sistema (kernel32, vcruntime140...) não estão no pacote e são ignoradas.
 *
 * @param list<string> $wanted
 *
 * @return list<string>
 */
function dependencies(ZipArchive $source, array $wanted): array
{
    $available = [];
    for ($i = 0; $i < $source->numFiles; $i++) {
        $entry = (string) $source->getNameIndex($i);
        if (!str_contains($entry, '/') && str_ends_with(strtolower($entry), '.dll')) {
            $available[strtolower($entry)] = $entry;
        }
    }

    $result = [];
    $queue = $wanted;
    while ($queue !== []) {
        $entry = array_shift($queue);
        if (in_array($entry, $result, true)) {
            continue;
        }
        $result[] = $entry;
        if (!preg_match('/\.(dll|exe)$/i', $entry)) {
            continue;
        }
        foreach (peImports((string) $source->getFromName($entry)) as $import) {
            if (isset($available[$import])) {
                $queue[] = $available[$import];
            }
        }
    }

    return $result;
}

/**
 * Nomes (minúsculos) das DLLs na tabela de importação de um executável PE.
 *
 * @return list<string>
 */
function peImports(string $data): array
{
    if (strlen($data) < 0x40 || !str_starts_with($data, 'MZ')) {
        return [];
    }
    $pe = unpack('V', $data, 0x3C)[1];
    if (substr($data, $pe, 4) !== "PE\0\0") {
        return [];
    }

    $coff = $pe + 4;
    $sectionCount = unpack('v', $data, $coff + 2)[1];
    $optionalSize = unpack('v', $data, $coff + 16)[1];
    $optional = $coff + 20;
    $isPe32Plus = unpack('v', $data, $optional)[1] === 0x20B;
    $importRva = unpack('V', $data, $optional + ($isPe32Plus ? 112 : 96) + 8)[1];
    $sections = $optional + $optionalSize;

    $toOffset = static function (int $rva) use ($data, $sectionCount, $sections): ?int {
        for ($i = 0; $i < $sectionCount; $i++) {
            [, $virtualSize, $virtualAddress, $rawSize, $rawPointer] = unpack('V4', $data, $sections + $i * 40 + 8);
            if ($rva >= $virtualAddress && $rva < $virtualAddress + max($virtualSize, $rawSize)) {
                return $rva - $virtualAddress + $rawPointer;
            }
        }

        return null;
    };

    $imports = [];
    $offset = $importRva > 0 ? $toOffset($importRva) : null;
    while ($offset !== null && $offset + 20 <= strlen($data)) {
        $nameRva = unpack('V', $data, $offset + 12)[1];
        if ($nameRva === 0) {
            break;
        }
        $nameOffset = $toOffset($nameRva);
        if ($nameOffset !== null) {
            $end = strpos($data, "\0", $nameOffset);
            $imports[] = strtolower(substr($data, $nameOffset, ($end === false ? strlen($data) : $end) - $nameOffset));
        }
        $offset += 20;
    }

    return $imports;
}

// ------------------------------------------------------------- utilitários

/**
 * @param array<string, string> $vars
 */
function render(string $template, array $vars = []): string
{
    $vars += ['VERSION' => VERSION];
    $text = (string) file_get_contents($template);
    foreach ($vars as $key => $value) {
        $text = str_replace('{{' . $key . '}}', $value, $text);
    }

    return $text;
}

function windowsText(string $text): string
{
    return str_replace(["\r\n", "\n"], ["\n", "\r\n"], $text);
}

/**
 * @return list<string> arquivos (ordenados) sob $dir; $extension null = todos
 */
function filesUnder(string $dir, ?string $extension = 'php'): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && ($extension === null || $file->getExtension() === $extension)) {
            $files[] = str_replace('\\', '/', $file->getPathname());
        }
    }
    sort($files);

    return $files;
}

/**
 * @return resource
 */
function httpContext()
{
    return stream_context_create(['http' => [
        'timeout' => 60,
        'follow_location' => 1,
        'user_agent' => 'snakecode-build/' . VERSION,
    ]]);
}

function download(string $url, string $destination): void
{
    $temporary = "{$destination}.part";
    if (!@copy($url, $temporary, httpContext())) {
        @unlink($temporary);
        fail("Falha ao baixar {$url}");
    }
    rename($temporary, $destination);
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        fail("Não foi possível criar {$dir}");
    }
}

function removeTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($dir);
}

function report(string $file): void
{
    printf("  %-48s %8.1f KB\n", basename($file), filesize($file) / 1024);
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
