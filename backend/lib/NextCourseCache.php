<?php

declare(strict_types=1);

final class NextCourseCache
{
    private readonly string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? __DIR__ . '/../storage/next-course.json';
    }

    public function read(): ?array
    {
        if (!is_file($this->path)) {
            return null;
        }

        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            return null;
        }

        $payload = json_decode($contents, true);
        $course = is_array($payload) ? ($payload['course'] ?? null) : null;
        if (!is_array($course)) {
            return null;
        }

        $date = (string) ($course['date'] ?? '');
        $courseDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $errors = DateTimeImmutable::getLastErrors();
        $dateIsValid = $courseDate !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));

        if (
            !$dateIsValid
            || $courseDate < new DateTimeImmutable('today')
            || trim((string) ($course['title'] ?? '')) === ''
            || !filter_var((string) ($course['url'] ?? ''), FILTER_VALIDATE_URL)
        ) {
            return null;
        }

        return $course;
    }

    public function write(?array $course): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the next-course cache directory.');
        }

        $json = json_encode([
            'fetchedAt' => date(DATE_ATOM),
            'course' => $course,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        $temporaryPath = tempnam($directory, 'next-course-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Unable to create a temporary next-course cache file.');
        }

        try {
            if (file_put_contents($temporaryPath, $json . PHP_EOL, LOCK_EX) === false) {
                throw new RuntimeException('Unable to write the next-course cache.');
            }

            @chmod($temporaryPath, 0644);
            if (!rename($temporaryPath, $this->path)) {
                throw new RuntimeException('Unable to publish the next-course cache.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }
}
