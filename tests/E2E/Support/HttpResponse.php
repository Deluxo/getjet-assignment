<?php

declare(strict_types=1);

namespace App\Tests\E2E\Support;

final readonly class HttpResponse
{
    /**
     * @param array<string, string> $headers lower-cased name => last value
     */
    public function __construct(
        public int $status,
        public string $body,
        public array $headers,
    ) {}

    /**
     * @throws \JsonException when the body is not valid JSON.
     */
    public function json(): mixed
    {
        return json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
