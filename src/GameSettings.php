<?php

declare(strict_types=1);

namespace SnakeCode;

/** Preferências ajustáveis no menu e guardadas entre sessões. */
final class GameSettings
{
    public const COLORS = ['green', 'cyan', 'yellow', 'magenta', 'red', 'blue'];

    public function __construct(
        public int $startLevel = 1,
        public ?string $projectPath = null,
        public int $fileCount = 40,
        public int $turnsPerFile = 1,
        public string $snakeColor = 'green',
        public bool $obstaclesEnabled = true,
    ) {
        $this->startLevel = max(1, min(100, $startLevel));
        $this->fileCount = max(1, min(500, $fileCount));
        $this->turnsPerFile = max(1, min(500, $turnsPerFile));
        if (!in_array($snakeColor, self::COLORS, true)) {
            $this->snakeColor = 'green';
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            is_numeric($data['start_level'] ?? null) ? (int) $data['start_level'] : 1,
            is_string($data['project_path'] ?? null) && $data['project_path'] !== '' ? $data['project_path'] : null,
            is_numeric($data['file_count'] ?? null) ? (int) $data['file_count'] : 40,
            is_numeric($data['turns_per_file'] ?? null) ? (int) $data['turns_per_file'] : 1,
            is_string($data['snake_color'] ?? null) ? $data['snake_color'] : 'green',
            ($data['obstacles_enabled'] ?? true) === true,
        );
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'start_level' => $this->startLevel,
            'project_path' => $this->projectPath,
            'file_count' => $this->fileCount,
            'turns_per_file' => $this->turnsPerFile,
            'snake_color' => $this->snakeColor,
            'obstacles_enabled' => $this->obstaclesEnabled,
        ];
    }
}
