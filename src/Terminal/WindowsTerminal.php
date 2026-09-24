<?php

declare(strict_types=1);

namespace SnakeCode\Terminal;

use FFI;
use FFI\CData;
use RuntimeException;

/**
 * Console nativo do Windows (Windows Terminal, PowerShell, cmd) via FFI + kernel32.
 *
 * - Saída: ENABLE_VIRTUAL_TERMINAL_PROCESSING faz o console entender as mesmas
 *   sequências ANSI usadas no Linux; code page UTF-8 para os símbolos da IDE.
 * - Entrada: sem modo de linha/eco/processamento (Ctrl+C vira tecla, como no Linux)
 *   e sem QuickEdit (clicar na janela não congela o jogo). O teclado é lido com
 *   WaitForSingleObject + ReadConsoleInputW e traduzido para bytes VT.
 * - Modos e code page originais são restaurados no finally do Game e no shutdown.
 */
final class WindowsTerminal extends BaseTerminal
{
    private const STD_INPUT_HANDLE = 4294967286;  // (DWORD) -10
    private const STD_OUTPUT_HANDLE = 4294967285; // (DWORD) -11

    private const ENABLE_WINDOW_INPUT = 0x0008;
    private const ENABLE_EXTENDED_FLAGS = 0x0080;
    private const ENABLE_PROCESSED_OUTPUT = 0x0001;
    private const ENABLE_VIRTUAL_TERMINAL_PROCESSING = 0x0004;
    private const DISABLE_NEWLINE_AUTO_RETURN = 0x0008;

    private const KEY_EVENT = 0x0001;
    private const WINDOW_BUFFER_SIZE_EVENT = 0x0004;
    private const WAIT_OBJECT_0 = 0;
    private const CP_UTF8 = 65001;
    private const MAX_EVENTS = 64;

    /** Códigos de tecla virtual das setas => sequência VT equivalente. */
    private const ARROWS = [0x26 => "\e[A", 0x28 => "\e[B", 0x27 => "\e[C", 0x25 => "\e[D"];

    private const DECLARATIONS = <<<'C'
        typedef unsigned short WORD;
        typedef uint32_t DWORD;
        typedef int BOOL;
        typedef unsigned int UINT;
        typedef unsigned short WCHAR;
        typedef void *HANDLE;
        typedef struct {
            BOOL bKeyDown;
            WORD wRepeatCount;
            WORD wVirtualKeyCode;
            WORD wVirtualScanCode;
            WCHAR UnicodeChar;
            DWORD dwControlKeyState;
        } KEY_EVENT_RECORD;
        typedef struct {
            WORD EventType;
            union { KEY_EVENT_RECORD KeyEvent; } Event;
        } INPUT_RECORD;
        typedef struct { short X; short Y; } COORD;
        typedef struct { short Left; short Top; short Right; short Bottom; } SMALL_RECT;
        typedef struct {
            COORD dwSize;
            COORD dwCursorPosition;
            WORD wAttributes;
            SMALL_RECT srWindow;
            COORD dwMaximumWindowSize;
        } CONSOLE_SCREEN_BUFFER_INFO;
        HANDLE GetStdHandle(DWORD nStdHandle);
        BOOL GetConsoleMode(HANDLE hConsoleHandle, DWORD *lpMode);
        BOOL SetConsoleMode(HANDLE hConsoleHandle, DWORD dwMode);
        DWORD WaitForSingleObject(HANDLE hHandle, DWORD dwMilliseconds);
        BOOL GetNumberOfConsoleInputEvents(HANDLE hConsoleInput, DWORD *lpcNumberOfEvents);
        BOOL ReadConsoleInputW(HANDLE hConsoleInput, INPUT_RECORD *lpBuffer, DWORD nLength, DWORD *lpNumberOfEventsRead);
        BOOL GetConsoleScreenBufferInfo(HANDLE hConsoleOutput, CONSOLE_SCREEN_BUFFER_INFO *lpConsoleScreenBufferInfo);
        UINT GetConsoleOutputCP(void);
        BOOL SetConsoleOutputCP(UINT wCodePageID);
        C;

    private ?FFI $kernel = null;
    private ?CData $input = null;
    private ?CData $output = null;
    private ?int $savedInputMode = null;
    private ?int $savedOutputMode = null;
    private ?int $savedCodePage = null;
    private bool $active = false;

    /**
     * Motivo pelo qual o console nativo não pode ser usado (null = pode).
     */
    public static function unavailableReason(): ?string
    {
        if (!extension_loaded('ffi')) {
            return 'No Windows o snakecode precisa da extensão FFI: habilite "extension=ffi" no php.ini.';
        }
        if (!in_array(strtolower((string) ini_get('ffi.enable')), ['1', 'true', 'on', 'preload'], true)) {
            return 'A extensão FFI está desativada: use "ffi.enable=true" no php.ini.';
        }

        return null;
    }

    public function enter(): void
    {
        $reason = self::unavailableReason();
        if ($reason !== null) {
            throw new RuntimeException($reason);
        }

        $this->kernel = FFI::cdef(self::DECLARATIONS, 'kernel32.dll');
        $this->input = $this->kernel->GetStdHandle(self::STD_INPUT_HANDLE);
        $this->output = $this->kernel->GetStdHandle(self::STD_OUTPUT_HANDLE);
        $this->savedInputMode = $this->consoleMode($this->input);
        $this->savedOutputMode = $this->consoleMode($this->output);
        if ($this->savedInputMode === null || $this->savedOutputMode === null) {
            throw new RuntimeException('Console do Windows não encontrado. Rode no Windows Terminal, PowerShell ou cmd (Git Bash/mintty não é suportado).');
        }
        $this->savedCodePage = $this->kernel->GetConsoleOutputCP();

        $this->active = true;
        register_shutdown_function([$this, 'restore']);
        if (function_exists('sapi_windows_set_ctrl_handler')) {
            // Ctrl+Break (Ctrl+C já chega como tecla).
            sapi_windows_set_ctrl_handler(function (): void {
                $this->interrupted = true;
            });
        }

        $this->kernel->SetConsoleOutputCP(self::CP_UTF8);
        $this->kernel->SetConsoleMode($this->input, self::ENABLE_WINDOW_INPUT | self::ENABLE_EXTENDED_FLAGS);
        $this->kernel->SetConsoleMode(
            $this->output,
            self::ENABLE_PROCESSED_OUTPUT | self::ENABLE_VIRTUAL_TERMINAL_PROCESSING | self::DISABLE_NEWLINE_AUTO_RETURN,
        );

        // Tela alternativa, cursor oculto, sem quebra automática de linha.
        $this->write("\e[?1049h\e[?25l\e[?7l\e[2J");
        $this->refreshSize();
    }

    public function restore(): void
    {
        if (!$this->active || $this->kernel === null) {
            return;
        }
        $this->active = false;

        // Ainda com VT ligado, para que as sequências de restauração sejam interpretadas.
        $this->write("\e[0m\e[?7h\e[?25h\e[?1049l");
        if ($this->savedOutputMode !== null) {
            $this->kernel->SetConsoleMode($this->output, $this->savedOutputMode);
        }
        if ($this->savedInputMode !== null) {
            $this->kernel->SetConsoleMode($this->input, $this->savedInputMode);
        }
        if ($this->savedCodePage !== null) {
            $this->kernel->SetConsoleOutputCP($this->savedCodePage);
        }
    }

    public function read(int $timeoutUs): string
    {
        if ($this->kernel === null || $this->input === null) {
            usleep($timeoutUs);

            return '';
        }

        if ($this->kernel->WaitForSingleObject($this->input, intdiv($timeoutUs + 999, 1000)) !== self::WAIT_OBJECT_0) {
            return '';
        }

        $pending = $this->kernel->new('DWORD');
        if (!$this->kernel->GetNumberOfConsoleInputEvents($this->input, FFI::addr($pending)) || $pending->cdata === 0) {
            return '';
        }

        $count = min($pending->cdata, self::MAX_EVENTS);
        $records = $this->kernel->new("INPUT_RECORD[{$count}]");
        $read = $this->kernel->new('DWORD');
        if (!$this->kernel->ReadConsoleInputW($this->input, FFI::addr($records[0]), $count, FFI::addr($read))) {
            return '';
        }

        $bytes = '';
        for ($i = 0; $i < $read->cdata; $i++) {
            $record = $records[$i];
            if ($record->EventType === self::WINDOW_BUFFER_SIZE_EVENT) {
                $this->resized = true;
            } elseif ($record->EventType === self::KEY_EVENT && $record->Event->KeyEvent->bKeyDown) {
                $bytes .= self::translateKey($record->Event->KeyEvent->wVirtualKeyCode, $record->Event->KeyEvent->UnicodeChar);
            }
        }

        return $bytes;
    }

    /**
     * Converte uma tecla do console do Windows nos mesmos bytes de um terminal VT,
     * para que o Input::parse funcione igual nos dois sistemas.
     */
    public static function translateKey(int $virtualKey, int $unicodeChar): string
    {
        if (isset(self::ARROWS[$virtualKey])) {
            return self::ARROWS[$virtualKey];
        }
        // 0 = tecla sem caractere (Shift, F1...); metades de surrogate são ignoradas.
        if ($unicodeChar === 0 || ($unicodeChar >= 0xD800 && $unicodeChar <= 0xDFFF)) {
            return '';
        }

        return mb_chr($unicodeChar, 'UTF-8');
    }

    protected function refreshSize(): void
    {
        if ($this->kernel === null || $this->output === null) {
            return;
        }

        $info = $this->kernel->new('CONSOLE_SCREEN_BUFFER_INFO');
        if ($this->kernel->GetConsoleScreenBufferInfo($this->output, FFI::addr($info))) {
            $this->cols = max(1, $info->srWindow->Right - $info->srWindow->Left + 1);
            $this->rows = max(1, $info->srWindow->Bottom - $info->srWindow->Top + 1);
        }
    }

    private function consoleMode(CData $handle): ?int
    {
        $mode = $this->kernel?->new('DWORD');
        if ($mode === null || !$this->kernel->GetConsoleMode($handle, FFI::addr($mode))) {
            return null;
        }

        return $mode->cdata;
    }
}
