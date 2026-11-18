<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shift\Http;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreateShiftTest extends WebTestCase
{
    public function testReturnsMethodNotAllowedForGet(): void
    {
        $client = self::createClient();
        $client->request('GET', '/shifts');

        self::assertSame(405, $client->getResponse()->getStatusCode());
    }

    public function testReturnsBadRequestForEmptyBody(): void
    {
        $client = self::createClient();
        $client->request('POST', '/shifts', [], [], ['CONTENT_TYPE' => 'application/json']);

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testReturnsBadRequestForMalformedJson(): void
    {
        $client = self::createClient();
        $client->request('POST', '/shifts', [], [], ['CONTENT_TYPE' => 'application/json'], '{invalid');

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testReturnsBadRequestForJsonArrayPayload(): void
    {
        $client = self::createClient();
        $client->request('POST', '/shifts', [], [], ['CONTENT_TYPE' => 'application/json'], '[1,2]');

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testReturnsBadRequestForMissingFields(): void
    {
        $client = self::createClient();
        $client->request('POST', '/shifts', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');

        self::assertSame(400, $client->getResponse()->getStatusCode());
    }

    public function testReturnsNotImplementedForValidPayload(): void
    {
        $client = self::createClient();
        $payload = json_encode([
            'employee_id' => 'employee-123',
            'date' => '2026-10-06',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'country_code' => 'LT',
        ]);

        $client->request('POST', '/shifts', [], [], ['CONTENT_TYPE' => 'application/json'], (string) $payload);

        self::assertSame(501, $client->getResponse()->getStatusCode());
    }
}
