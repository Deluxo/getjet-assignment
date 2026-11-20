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
        if (!str_contains($url, '/Holidays/LT/2026')) {
            return new MockResponse('[]');
        }

        return new MockResponse(
            '[{"date":"2026-01-01","nationalHoliday":true,"holidayTypes":["Public"]}]',
        );
    }
}
