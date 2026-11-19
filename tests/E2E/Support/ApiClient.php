<?php

declare(strict_types=1);

namespace App\Tests\E2E\Support;

use RuntimeException;

final class ApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout = 10,
    ) {}

    /**
     * @param array<string, string> $headers header name => value
     */
    public function request(string $method, string $path, array $headers = [], ?string $body = null): HttpResponse
    {
        $headersOut = [];

        $handle = curl_init();
        curl_setopt_array($handle, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $header) use (&$headersOut): int {
                $parts = explode(':', $header, 2);

                if (count($parts) === 2) {
                    $headersOut[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return strlen($header);
            },
        ]);

        if ($headers !== []) {
            $curlHeaders = [];

            foreach ($headers as $name => $value) {
                $curlHeaders[] = sprintf('%s: %s', $name, $value);
            }

            curl_setopt($handle, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);

        if ($raw === false) {
            $error = curl_error($handle);

            throw new RuntimeException(sprintf('Request to %s%s failed: %s', $this->baseUrl, $path, $error));
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($status, (string) $raw, $headersOut);
    }

    public function get(string $path): HttpResponse
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function postJson(string $path, array $payload): HttpResponse
    {
        return $this->request(
            'POST',
            $path,
            ['Content-Type' => 'application/json'],
            (string) json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
