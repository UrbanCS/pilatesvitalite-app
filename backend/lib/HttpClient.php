<?php

declare(strict_types=1);

final class HttpClient
{
    public function __construct(private readonly int $timeout = 15)
    {
    }

    public function get(string $url): string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'PilatesVitalitePWA/1.0 (+https://pilatesvitalite.ca)',
                CURLOPT_HTTPHEADER => ['Accept: text/html,application/json;q=0.9,*/*;q=0.8'],
            ]);

            $body = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            curl_close($handle);

            if ($body === false || $status < 200 || $status >= 400) {
                throw new RuntimeException(sprintf('HTTP fetch failed for %s (%s %s)', $url, $status, $error));
            }

            return (string) $body;
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'header' => "User-Agent: PilatesVitalitePWA/1.0\r\nAccept: text/html,application/json\r\n",
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException(sprintf('HTTP fetch failed for %s', $url));
        }

        return $body;
    }
}

