<?php

declare(strict_types=1);

namespace SnakeCode\Tests\Editor;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SnakeCode\Editor\ProjectScanner;

final class ProjectScannerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/snakecode-scan-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() && !$entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->dir);
    }

    public function testKeepsOnlyCodeWrittenForTheProject(): void
    {
        foreach ([
            'app/Models/User.php', 'resources/js/app.js', 'resources/views/home.blade.php',
            'vendor/laravel/framework/Str.php', 'node_modules/lodash/index.js', 'storage/framework/views/x.php',
            '.idea/workspace.php', 'public/logo.png', 'docs/manual.pdf', 'README.md', '.env', 'composer.lock',
            'public/js/app.min.js', 'config/secrets.php', 'bootstrap/cache/services.php',
        ] as $file) {
            $this->file($file);
        }

        self::assertSame(
            ['app/Models/User.php', 'resources/js/app.js', 'resources/views/home.blade.php'],
            $this->scan(),
        );
    }

    public function testMostRecentlyEditedFirstAndLimited(): void
    {
        $this->file('a.php', mtime: 1_000);
        $this->file('b.php', mtime: 3_000);
        $this->file('c.php', mtime: 2_000);
        $this->file('d.php', mtime: 4_000);

        self::assertSame(['d.php', 'b.php', 'c.php'], $this->scan(3));
    }

    public function testSkipsEmptyAndHugeFiles(): void
    {
        $this->file('empty.php', '');
        $this->file('huge.js', str_repeat('x', 200_000));
        $this->file('ok.ts', 'const a = 1;');

        self::assertSame(['ok.ts'], $this->scan());
    }

    public function testRespectsGitignoreInsideRepositories(): void
    {
        exec('git --version 2>/dev/null', $output, $status);
        if ($status !== 0) {
            self::markTestSkipped('git indisponível');
        }

        exec('git -C ' . escapeshellarg($this->dir) . ' init -q 2>/dev/null', $output, $status);
        self::assertSame(0, $status);
        $this->file('.gitignore', "generated/\n");
        $this->file('generated/Api.php');
        $this->file('app/Service.php');

        self::assertSame(['app/Service.php'], $this->scan());
    }

    /**
     * @return list<string> caminhos relativos, ordenados alfabeticamente quando os mtimes empatam
     */
    private function scan(int $limit = ProjectScanner::DEFAULT_LIMIT): array
    {
        $files = array_map(
            fn (string $path): string => substr($path, strlen($this->dir) + 1),
            (new ProjectScanner())->scan($this->dir, $limit),
        );
        if ($limit === ProjectScanner::DEFAULT_LIMIT) {
            sort($files);
        }

        return $files;
    }

    private function file(string $relative, string $content = "<?php\n", ?int $mtime = null): void
    {
        $path = $this->dir . '/' . $relative;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $content);
        if ($mtime !== null) {
            touch($path, $mtime);
        }
    }
}
