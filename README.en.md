# SnakeCode

[Português (Brasil)](README.md)

A little Snake game hiding in a terminal IDE. The snake moves through source code: the block cursor is its head and a red linter underline is its food. Play over SnakeCode's own code or point it at another project.

![SnakeCode running in a terminal with project source code in the editor](assets/snakecode-game.png)

## 1. Build the packages

The packaging script creates three formats: a PHAR for PHP, a Linux/macOS/WSL archive and a Windows 64-bit installer. The output goes to `dist/`, which Git ignores.

To build all packages, use PHP 8.3 or newer with the `phar` and `zip` extensions enabled. The Windows package downloads the official portable PHP build and checks its published SHA-256, so the build needs internet access.

```bash
php8.3 -d phar.readonly=0 build/package.php
```

If your PHP command is named `php`, use `php` instead of `php8.3`. To build only the PHAR and Linux package, without downloading PHP for Windows, run:

```bash
php8.3 -d phar.readonly=0 build/package.php --no-windows
```

When it finishes, `dist/` contains `snakecode.phar`, `snakecode-1.0.0-linux.tar.gz` and, unless you used `--no-windows`, `snakecode-1.0.0-windows-x64.zip`.

## 2. Install

### Linux, macOS and WSL

The package requires PHP 8.3 or newer with `mbstring` and `tokenizer`. Extract it and run the installer:

```bash
tar -xzf dist/snakecode-1.0.0-linux.tar.gz
cd snakecode-1.0.0-linux
sh install.sh
```

The installer puts SnakeCode under `~/.local` and does not need sudo. If it says `~/.local/bin` is missing from `PATH`, add the line it prints to your `~/.bashrc` and open a new terminal. Then run `snakecode`.

### Windows 10/11 (x64)

Extract the ZIP. In the `SnakeCode` folder, double-click `install.cmd`. The package includes portable PHP 8.3, so you do not need to install PHP. The installer adds `snakecode` to your user `PATH` and creates a Start Menu shortcut. Open a new terminal and run `snakecode`.

Use Windows Terminal, PowerShell or cmd. Git Bash and mintty are not supported. If SnakeCode reports that the Microsoft Visual C++ Redistributable 2015–2022 x64 is missing, install it from [Microsoft](https://aka.ms/vs/17/release/vc_redist.x64.exe).

### Run the PHAR

If PHP 8.3 or newer is already installed, you can run the PHAR directly without installing SnakeCode:

```bash
php dist/snakecode.phar
```

On Windows, PHP needs FFI enabled (`extension=ffi` and `ffi.enable=true`), and the game must run in Windows Terminal, PowerShell or cmd.

## 3. Run from source, without an installer

To run a cloned checkout, use PHP 8.3 or newer with `mbstring` and `tokenizer`. You do not need `composer install` just to play; `bootstrap.php` loads the app classes.

```bash
php8.3 bin/snakecode
```

On Windows, run this from PowerShell or cmd:

```powershell
php bin\snakecode
```

To run from source on Windows, enable FFI in `php.ini` (`extension=ffi` and `ffi.enable=true`) and use Windows Terminal, PowerShell or cmd. Git Bash and mintty are not supported.

The game uses SnakeCode's own source as the backdrop. Press `q` or `Ctrl+C` to quit.

## 4. Play over another project

Pass the project directory as an argument. The same pattern works with an installed copy, the PHAR or the source checkout:

```bash
snakecode /path/to/my-project
php8.3 bin/snakecode /path/to/my-project
php dist/snakecode.phar /path/to/my-project
```

On Windows, quote paths that contain spaces:

```powershell
snakecode "C:\Users\Cesar\my project"
php bin\snakecode "C:\Users\Cesar\my project"
```

You can choose how many files appear and how subtle the snake looks:

```bash
snakecode --files=80 --stealth=medium /path/to/my-project
```

In a Git repository, SnakeCode reads tracked files and new files that are not ignored by `.gitignore`. Outside Git, it skips `vendor/`, `node_modules/`, `storage/`, `cache/`, `dist/`, `build/`, `coverage/`, hidden directories and other dependency folders. It shows source files up to 128 KB; `.env*` and files named like `secrets.php` or `credentials.js` are skipped.

By default, SnakeCode picks up to 40 source files, most recently edited first. It reads each file only when its tab opens.

## Controls

| Key | Action |
|---|---|
| Arrow keys, WASD or hjkl | Move; the first key starts the game |
| `m` | Open the menu and pause |
| `p` or Space | Pause ("Paused on breakpoint") |
| `v` | Cycle through `subtle`, `medium` and `easy` |
| `Esc` or `` ` `` | Hide the game and leave only code visible; press again to return paused |
| `Enter` | Restart after game over |
| `q` or Ctrl+C | Quit and restore the terminal |

In the menu, use ↑ / ↓ and Enter to choose an option; ← / → change the stealth profile. Add `--no-menu` to the command line to skip the menu.

## What the editor cues mean

| IDE detail | In the game |
|---|---|
| Block cursor | Snake's head |
| Selection behind the cursor | Snake's body |
| Red wavy underline | Food |
| `●` in the gutter and `Ln X, Col Y` in the status bar | Food's line and column |
| `■ Undefined variable $food at col N` | Hint on the food's line |
| `▸ ⋯ N lines` (folded region) | Obstacle, starting at level 3 |
| Branch `feature/level-N` | Current level |
| A new tab | Each turn opens another project file |
| TERMINAL panel with `PHP Fatal error` | Game over, with the collision's stack trace |

## Tests

```bash
composer install
composer test
```
