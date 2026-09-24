# SnakeCode

[Português (Brasil)](README.md)

A little Snake game hiding in a terminal IDE. It looks like an editor, complete with tabs, syntax highlighting, breadcrumbs and a status bar. The snake is right there in the cursor.

```bash
snakecode                           # use SnakeCode's own source as the backdrop
snakecode /path/to/your/project     # or play over your own code
```

## Install

Prebuilt packages are in `dist/` (see [building the packages](#building-the-packages)).

| Platform | Package | Install |
|---|---|---|
| Windows 10/11 (x64) | `snakecode-1.0.0-windows-x64.zip` | Extract it and run `install.cmd`. PHP 8.3 is included. |
| Linux, macOS or WSL | `snakecode-1.0.0-linux.tar.gz` | Run `tar xzf ...` and then `sh install.sh`. Installs under `~/.local`, no sudo needed. Requires PHP 8.3+ with `mbstring`. |
| Any platform with PHP 8.3+ | `snakecode.phar` | Run `php snakecode.phar [directory]`. On Windows, PHP needs `extension=ffi` and `ffi.enable=true`. |

On Windows, SnakeCode runs in Windows Terminal, PowerShell or cmd. The installer adds `snakecode` to PATH and creates a Start Menu shortcut. Git Bash and mintty are not supported. To run from source, use `php8.3 bin/snakecode` on Linux or `php bin\snakecode` on Windows.

### Building the packages

```bash
php8.3 -d phar.readonly=0 build/package.php               # phar, Linux and Windows
php8.3 -d phar.readonly=0 build/package.php --no-windows  # skip the Windows PHP download
```

The Windows package uses the official `nts-vs16-x64` build from windows.php.net. The build script checks its SHA-256 against the published checksum and includes only the PHP executable, required extensions and their DLL dependencies.

## Play over your own project

Pass a project directory and SnakeCode will use its source files as the editor backdrop. Tabs, breadcrumbs and the status bar show paths and languages relative to that directory.

- In a Git repository, SnakeCode reads tracked files and new, unignored files, following `.gitignore`. Outside Git, it skips folders such as `vendor/`, `node_modules/`, `storage/`, `cache/`, `dist/`, `build/`, `coverage/` and hidden directories.
- It reads source code in PHP, Blade, JS/TS, Vue, Svelte, HTML, CSS/SCSS, Python, Ruby, Go, Rust, Java, Kotlin, C#, C/C++, Swift, SQL and shell. It skips media, documents, lock files, JSON/YAML, minified files, bundles and files larger than 128 KB.
- `.env*` files and files named like `secrets.php` or `credentials.js` are never shown.
- The default is 40 files, most recently edited first. Change that with `--files=N`.
- Files are read when their tabs open, so large projects do not slow startup.

## Reading the screen

| IDE detail | In the game |
|---|---|
| Block cursor | Snake's head |
| Selection behind the cursor | Snake's body |
| Red wavy linter underline | Food |
| `●` in the gutter and `Ln X, Col Y` in the status bar | The food's exact line and column |
| `■ Undefined variable $food at col N` | An inline hint at the food's line |
| `▸ ⋯ N lines` (folded region) | An obstacle, starting at level 3 |
| Branch `feature/level-N` | Current level |
| `⟳ deaths↓ score↑` and `✓ food/goal` | Scoreboard |
| A new tab | Each turn opens another workspace file |
| TERMINAL panel with `PHP Fatal error` | Game over, with the collision's real stack trace |

## Menu and controls

The game opens on a Welcome tab. The menu lets you start, choose a stealth profile, view the controls and see a live preview of the editor cues. Press `m` during a game to pause and open it; you can resume or start a new game. Use `--no-menu` to skip it.

| Key | Action |
|---|---|
| ↑ / ↓ and Enter in the menu | Choose an option; ← / → change the stealth profile |
| `m` | Open the menu and pause |
| Arrow keys, WASD or hjkl | Move; the first key starts the game |
| `p` or Space | Pause ("Paused on breakpoint") |
| `v` | Cycle through `subtle`, `medium` and `easy` |
| `Esc` or `` ` `` | Panic key: hide the game and leave only code visible; press again to return paused |
| `Enter` | Restart after game over |
| `q` or Ctrl+C | Quit and restore the terminal |

## Options

```text
PROJECT_DIRECTORY              source used as the backdrop (default: SnakeCode itself)
--files=N                      number of files, 1 to 500 (default: 40)
--stealth=subtle|medium|easy   stealth profile (the last choice is saved)
--no-menu                      skip the opening menu
--seed=N                       deterministic random seed
--render-once                  print one frame and exit (no TTY required)
```

Options can go before or after the project directory, in `--name=value` form. The high score and selected profile are saved to `~/.local/state/snakecode/state.json`, with directory permissions `0700`, file permissions `0600` and atomic writes. If `kill -9` leaves the terminal in a bad state, run `reset`.

## Tests

```bash
php8.3 /usr/local/bin/composer install
php8.3 vendor/bin/phpunit
```
