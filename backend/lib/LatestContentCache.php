<?php

declare(strict_types=1);

final class LatestContentCache
{
    private readonly string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? __DIR__ . '/../storage/latest-content.json';
    }

    public function read(int $maxAgeSeconds): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        $modifiedAt = @filemtime($this->path);
        if ($modifiedAt === false || (time() - $modifiedAt) > max(1, $maxAgeSeconds)) {
            return null;
        }

        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            return null;
        }

        $payload = json_decode($contents, true);
        $latest = is_array($payload) ? ($payload['latest'] ?? null) : null;

        return is_array($latest) && $this->isValid($latest) ? $latest : null;
    }

    public function write(array $latest): void
    {
        if (!$this->isValid($latest)) {
            throw new InvalidArgumentException('Invalid latest-content cache payload.');
        }

        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the latest-content cache directory.');
        }

        $json = json_encode([
            'cachedAt' => date(DATE_ATOM),
            'latest' => $latest,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $temporaryPath = tempnam($directory, 'latest-content-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary latest-content cache file.');
        }

        try {
            if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the latest-content cache.');
            }

            @chmod($temporaryPath, 0644);
            if (!rename($temporaryPath, $this->path)) {
                throw new RuntimeException('Unable to publish the latest-content cache.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function isValid(array $latest): bool
    {
        return isset($latest['site'], $latest['home'], $latest['about'], $latest['blog'], $latest['contact'])
            && is_array($latest['site'])
            && is_array($latest['home'])
            && is_array($latest['about'])
            && is_array($latest['blog'])
            && is_array($latest['contact'])
            && isset($latest['blog']['articles'])
            && is_array($latest['blog']['articles']);
    }
}
