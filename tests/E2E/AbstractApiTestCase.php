<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Tests\E2E\Support\ApiClient;
use App\Tests\E2E\Support\ApiServer;
use App\Tests\E2E\Support\HttpResponse;
use PHPUnit\Framework\TestCase;

/**
 * Battery-included base class for E2E tests.
 *
 * Boots the app with PHP's built-in server in a free port (see {@see ApiServer})
 * so subclasses can exercise real HTTP, and ships the request + assertion
 * helpers a typical API test needs. Point the suite at an existing server
 * instead of spawning one by setting E2E_BASE_URL.
 *
 * @abstract
 */
abstract class AbstractApiTestCase extends TestCase
{
    private static ?ApiServer $server = null;

    private static string $baseUrl = '';

    private ApiClient $client;

    public static function setUpBeforeClass(): void
    {
        $external = getenv('E2E_BASE_URL');

        if (is_string($external) && $external !== '') {
            self::$baseUrl = rtrim($external, '/');

            return;
        }

        self::$server = new ApiServer(self::projectDir());
        self::$server->start();
        self::$baseUrl = self::$server->baseUrl();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
        self::$baseUrl = '';
    }

    protected function setUp(): void
    {
        $this->client = new ApiClient(self::$baseUrl);
    }

    protected function client(): ApiClient
    {
        return $this->client;
    }

    protected function get(string $path): HttpResponse
    {
        return $this->client->get($path);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function postJson(string $path, array $payload): HttpResponse
    {
        return $this->client->postJson($path, $payload);
    }

    /**
     * A unique id so repeated runs (and your inotify loop) never collide.
     */
    protected function uniqueEmployeeId(): string
    {
        return 'employee-' . bin2hex(random_bytes(4));
    }

    /**
     * Assert a 201 Created with a JSON object body and return the decoded body.
     *
     * @return array<string, mixed>
     */
    protected function assertCreated(HttpResponse $response): array
    {
        $this->assertNoServerError($response);
        self::assertSame(201, $response->status, 'Expected a 201 Created response.');

        $body = $response->json();
        self::assertIsArray($body, 'The 201 body should be a JSON object.');

        return $body;
    }

    /**
     * Assert an error response with a non-empty "errors" array and return the decoded body.
     *
     * @return array<string, mixed>
     */
    protected function assertRejected(HttpResponse $response, int $status = 400): array
    {
        $this->assertNoServerError($response);
        self::assertSame($status, $response->status, sprintf('Expected a %d response.', $status));

        $body = $response->json();
        self::assertIsArray($body, 'The error body should be a JSON object.');
        self::assertIsArray($body['errors'] ?? null, 'The error body should contain an "errors" array.');
        self::assertNotSame([], $body['errors'], 'The "errors" array should not be empty.');

        return $body;
    }

    /**
     * Fail with a snippet of the server log when the app 5xx'd, so the real
     * exception is visible without digging through the built-in server.
     */
    protected function assertNoServerError(HttpResponse $response): void
    {
        if ($response->status < 500 || self::$server === null) {
            return;
        }

        $this->fail(
            sprintf(
                'The server responded with %d. Server log tail:%s%s',
                $response->status,
                PHP_EOL,
                self::$server->logTail(),
            ),
        );
    }

    private static function projectDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
