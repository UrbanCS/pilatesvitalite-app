<?php

declare(strict_types=1);

final class SourceNewsletterRepository
{
    public function __construct(private readonly array $config)
    {
    }

    public function sentCampaignItems(): array
    {
        $envPath = trim((string) ($this->config['SOURCE_APP_ENV_PATH'] ?? ''));
        if ($envPath === '') {
            return [];
        }

        $environment = $this->parseEnvironmentFile($envPath);
        $pdo = $this->connect($environment);
        $limit = max(1, min(5000, (int) ($this->config['MAX_NEWSLETTER_ITEMS'] ?? 500)));

        try {
            $statement = $pdo->query(
                "SELECT id, subject, status, sent_at, updated_at
                 FROM newsletter_campaigns
                 WHERE status = 'sent'
                 ORDER BY id DESC
                 LIMIT " . $limit
            );
            $rows = $statement->fetchAll();
        } catch (Throwable) {
            throw new RuntimeException('Impossible de lire les campagnes infolettre envoyees.');
        }

        $sourceUrl = rtrim((string) ($this->config['SOURCE_BASE_URL'] ?? ''), '/') . '/';
        $items = [];

        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $safeId = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $id) ?? '';
            $safeId = trim($safeId, '-');
            if ($safeId === '') {
                $safeId = hash('sha256', $id);
            }

            $subject = app_clean_text((string) ($row['subject'] ?? ''));
            $sentAt = trim((string) ($row['sent_at'] ?? ''));
            $updatedAt = trim((string) ($row['updated_at'] ?? ''));
            $payload = [
                'id' => $id,
                'subject' => $subject,
                'status' => 'sent',
                'sentAt' => $sentAt,
                'updatedAt' => $updatedAt,
            ];

            $items[] = [
                'section' => 'newsletter',
                'item_key' => 'campaign:' . $safeId,
                'source_url' => $sourceUrl,
                'content_hash' => app_json_hash($payload),
                'title' => $subject !== '' ? $subject : 'Infolettre Pilates Vitalite',
                'excerpt' => null,
                'image_url' => null,
                'published_at' => $sentAt !== '' ? $sentAt : null,
                'payload' => $payload,
            ];
        }

        return $items;
    }

    private function parseEnvironmentFile(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('SOURCE_APP_ENV_PATH ne pointe pas vers un fichier lisible.');
        }

        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('Impossible de lire le fichier environnement source.');
        }

        $environment = [];
        foreach ($lines as $index => $line) {
            if ($index === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            }

            $line = ltrim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = ltrim(substr($line, 7));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            $rawValue = ltrim(substr($line, $separator + 1));
            $environment[$key] = $this->parseEnvironmentValue($rawValue, $environment);
        }

        return $environment;
    }

    private function parseEnvironmentValue(string $rawValue, array $environment): string
    {
        if ($rawValue === '') {
            return '';
        }

        $first = $rawValue[0];
        if ($first === "'" || $first === '"') {
            $value = '';
            $escaped = false;
            $length = strlen($rawValue);

            for ($index = 1; $index < $length; $index++) {
                $character = $rawValue[$index];

                if ($escaped) {
                    if ($first === '"') {
                        $value .= match ($character) {
                            'n' => "\n",
                            'r' => "\r",
                            't' => "\t",
                            default => $character,
                        };
                    } elseif ($character === "'" || $character === '\\') {
                        $value .= $character;
                    } else {
                        $value .= '\\' . $character;
                    }

                    $escaped = false;
                    continue;
                }

                if ($character === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($character === $first) {
                    return $first === '"' ? $this->expandEnvironmentVariables($value, $environment) : $value;
                }

                $value .= $character;
            }

            if ($escaped) {
                $value .= '\\';
            }

            return $first === '"' ? $this->expandEnvironmentVariables($value, $environment) : $value;
        }

        $value = preg_replace('/\s+#.*$/', '', $rawValue) ?? $rawValue;

        return $this->expandEnvironmentVariables(trim($value), $environment);
    }

    private function expandEnvironmentVariables(string $value, array $environment): string
    {
        return preg_replace_callback(
            '/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/',
            static function (array $matches) use ($environment): string {
                $key = $matches[1];
                if (array_key_exists($key, $environment)) {
                    return (string) $environment[$key];
                }

                throw new RuntimeException('Une reference du fichier environnement source ne peut pas etre resolue.');
            },
            $value
        ) ?? $value;
    }

    private function connect(array $environment): PDO
    {
        $connection = strtolower(trim((string) ($environment['DB_CONNECTION'] ?? 'mysql')));
        if (!in_array($connection, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('La base source des infolettres doit utiliser MySQL ou MariaDB.');
        }

        $database = trim((string) ($environment['DB_DATABASE'] ?? ''));
        $username = (string) ($environment['DB_USERNAME'] ?? '');
        $password = (string) ($environment['DB_PASSWORD'] ?? '');
        if ($database === '' || $username === '') {
            throw new RuntimeException('La configuration de la base source est incomplete.');
        }

        $charset = trim((string) ($environment['DB_CHARSET'] ?? 'utf8mb4'));
        if (!preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
            throw new RuntimeException('Le jeu de caracteres de la base source est invalide.');
        }

        $socket = trim((string) ($environment['DB_SOCKET'] ?? ''));
        if ($socket !== '') {
            $dsn = sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $database, $charset);
        } else {
            $host = trim((string) ($environment['DB_HOST'] ?? '127.0.0.1'));
            $port = trim((string) ($environment['DB_PORT'] ?? '3306'));
            if ($host === '' || !ctype_digit($port) || (int) $port < 1 || (int) $port > 65535) {
                throw new RuntimeException('La configuration reseau de la base source est invalide.');
            }

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, (int) $port, $database, $charset);
        }

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable) {
            throw new RuntimeException('Impossible de se connecter a la base source des infolettres.');
        }
    }
}
