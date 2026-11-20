<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class NagerApiMockResponseFactory
{
    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        // Sentinel year used by the resilience tests to simulate an outage.
        if (str_contains($url, '/Holidays/LT/2099')) {
            return new MockResponse('upstream unavailable', ['http_code' => 500]);
        }

        if (!str_contains($url, '/Holidays/LT/2026')) {
            return new MockResponse('[]');
        }

        return MockResponse::fromFile(__DIR__.'/Fixtures/lt-2026.json');
    }
}
