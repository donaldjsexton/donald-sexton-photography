<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use SplFileInfo;

class LogReader
{
    /**
     * Maximum bytes to tail from the end of a log file.
     */
    private const MAX_BYTES = 524_288;

    /**
     * Recognised Monolog log levels, in severity order.
     *
     * @var list<string>
     */
    public const LEVELS = [
        'EMERGENCY',
        'ALERT',
        'CRITICAL',
        'ERROR',
        'WARNING',
        'NOTICE',
        'INFO',
        'DEBUG',
    ];

    /**
     * List every log file in storage/logs ordered by most recently modified.
     *
     * @return Collection<int, array{name: string, size: int, modified_at: Carbon}>
     */
    public function files(): Collection
    {
        $directory = storage_path('logs');

        if (! is_dir($directory)) {
            return collect();
        }

        return collect(glob($directory.DIRECTORY_SEPARATOR.'*.log') ?: [])
            ->map(fn (string $path): SplFileInfo => new SplFileInfo($path))
            ->filter(fn (SplFileInfo $file): bool => $file->isFile())
            ->sortByDesc(fn (SplFileInfo $file): int => $file->getMTime())
            ->map(fn (SplFileInfo $file): array => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => Carbon::createFromTimestamp($file->getMTime()),
            ])
            ->values();
    }

    /**
     * Resolve a log file name to an absolute path inside storage/logs.
     */
    public function path(string $name): ?string
    {
        $directory = storage_path('logs');
        $candidate = realpath($directory.DIRECTORY_SEPARATOR.$name);
        $base = realpath($directory);

        if ($candidate === false || $base === false) {
            return null;
        }

        if (! str_starts_with($candidate, $base.DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! str_ends_with($candidate, '.log')) {
            return null;
        }

        return $candidate;
    }

    /**
     * Parse the tail of a log file into structured entries (newest first).
     *
     * @return array{
     *     entries: list<array{timestamp: ?Carbon, environment: ?string, level: string, message: string, context: ?string}>,
     *     truncated: bool,
     *     bytes_read: int,
     * }
     */
    public function entries(string $path): array
    {
        $size = (int) @filesize($path);

        if ($size <= 0) {
            return ['entries' => [], 'truncated' => false, 'bytes_read' => 0];
        }

        $bytesToRead = min($size, self::MAX_BYTES);
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return ['entries' => [], 'truncated' => false, 'bytes_read' => 0];
        }

        try {
            fseek($handle, -$bytesToRead, SEEK_END);
            $contents = (string) fread($handle, $bytesToRead);
        } finally {
            fclose($handle);
        }

        if ($bytesToRead < $size) {
            $newlinePos = strpos($contents, "\n");
            if ($newlinePos !== false) {
                $contents = substr($contents, $newlinePos + 1);
            }
        }

        $entries = $this->parse($contents);

        return [
            'entries' => array_reverse($entries),
            'truncated' => $bytesToRead < $size,
            'bytes_read' => strlen($contents),
        ];
    }

    /**
     * @return list<array{timestamp: ?Carbon, environment: ?string, level: string, message: string, context: ?string}>
     */
    private function parse(string $contents): array
    {
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:?\d{2}|Z)?)\] (?<env>[\w-]+)\.(?<level>[A-Z]+): (?<rest>.*?)(?=^\[\d{4}-\d{2}-\d{2}|\z)/sm';

        if (preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $entries = [];

        foreach ($matches as $match) {
            $timestamp = null;
            try {
                $timestamp = Carbon::parse($match[1]);
            } catch (\Throwable) {
                $timestamp = null;
            }

            $rest = trim((string) $match['rest']);
            [$message, $context] = $this->splitMessage($rest);

            $entries[] = [
                'timestamp' => $timestamp,
                'environment' => $match['env'] ?: null,
                'level' => strtoupper((string) $match['level']),
                'message' => $message,
                'context' => $context,
            ];
        }

        return $entries;
    }

    /**
     * Split the body of a log entry into a one-line message and an optional
     * context blob (JSON payload + stack trace).
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitMessage(string $body): array
    {
        $newlinePos = strpos($body, "\n");
        $firstLine = $newlinePos === false ? $body : substr($body, 0, $newlinePos);
        $remainder = $newlinePos === false ? '' : trim(substr($body, $newlinePos + 1));

        $bracePos = strpos($firstLine, ' {"');
        if ($bracePos !== false) {
            $contextHead = substr($firstLine, $bracePos + 1);
            $message = rtrim(substr($firstLine, 0, $bracePos));
            $context = trim($contextHead.($remainder !== '' ? "\n".$remainder : ''));

            return [$message, $context !== '' ? $context : null];
        }

        return [$firstLine, $remainder !== '' ? $remainder : null];
    }
}
