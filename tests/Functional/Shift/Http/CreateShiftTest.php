<?php

declare(strict_types=1);

namespace App\Tests\Functional\Shift\Http;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

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

    public function testCreatesAShift(): void
    {
        $payload = self::payload();

        $response = self::post($payload);

        self::assertSame(201, $response->getStatusCode());

        $body = self::body($response);

        self::assertSame($payload['employee_id'], $body['employee_id']);
        self::assertSame('2026-10-06', $body['date']);
        self::assertSame('09:00', $body['start_time']);
        self::assertSame('17:00', $body['end_time']);
        self::assertSame('LT', $body['country_code']);

        $id = $body['id'] ?? null;
        self::assertIsString($id);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $id,
        );
    }

    public function testRejectsAnOverlappingShift(): void
    {
        $payload = self::payload();
        $client = self::createClient();

        self::assertSame(201, self::send($client, $payload)->getStatusCode());

        $response = self::send($client, ['start_time' => '16:00', 'end_time' => '18:00'] + $payload);

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('shift_overlap', self::firstErrorCode($response));
    }

    public function testAllowsAShiftThatStartsWhenAnotherEnds(): void
    {
        $payload = self::payload();
        $client = self::createClient();

        self::assertSame(201, self::send($client, $payload)->getStatusCode());
        self::assertSame(201, self::send($client, ['start_time' => '17:00', 'end_time' => '18:00'] + $payload)->getStatusCode());
    }

    public function testRejectsAPublicHoliday(): void
    {
        $response = self::post(['date' => '2026-12-25'] + self::payload());

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('date', self::firstErrorCode($response));
    }

    public function testRejectsAnUnsupportedCountry(): void
    {
        $response = self::post(['country_code' => 'EE'] + self::payload());

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('countryCode', self::firstErrorCode($response));
    }

    public function testRejectsAnEmptyEmployeeId(): void
    {
        $response = self::post(['employee_id' => ' '] + self::payload());

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('employeeId', self::firstErrorCode($response));
    }

    public function testRejectsAShiftLongerThanTwelveHours(): void
    {
        $response = self::post(['start_time' => '08:00', 'end_time' => '20:01'] + self::payload());

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('endTime', self::firstErrorCode($response));
    }

    public function testReturnsServiceUnavailableWhenTheHolidayCalendarFails(): void
    {
        $response = self::post(['date' => '2099-01-02'] + self::payload());

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('holiday_calendar', self::firstErrorCode($response));
    }

    /**
     * @param array<string, string> $payload
     */
    private static function post(array $payload): Response
    {
        return self::send(self::createClient(), $payload);
    }

    /**
     * @param array<string, string> $payload
     */
    private static function send(KernelBrowser $client, array $payload): Response
    {
        $client->request(
            'POST',
            '/shifts',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($payload),
        );

        return $client->getResponse();
    }

    /**
     * @return array<mixed, mixed>
     */
    private static function body(Response $response): array
    {
        $body = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!\is_array($body)) {
            throw new \LogicException('Expected a JSON object response.');
        }

        return $body;
    }

    private static function firstErrorCode(Response $response): mixed
    {
        $errors = self::body($response)['errors'] ?? null;

        if (!\is_array($errors) || !\is_array($errors[0] ?? null)) {
            throw new \LogicException('Expected an "errors" array.');
        }

        return $errors[0]['code'] ?? null;
    }

    /**
     * @return array<string, string>
     */
    private static function payload(): array
    {
        return [
            'employee_id' => 'employee-'.bin2hex(random_bytes(4)),
            'date' => '2026-10-06',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'country_code' => 'LT',
        ];
    }
}
