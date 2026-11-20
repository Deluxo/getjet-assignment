<?php

declare(strict_types=1);

namespace App\Tests\E2E\Shift\Http;

use App\Tests\E2E\AbstractApiTestCase;
use App\Tests\E2E\Support\HttpResponse;

/**
 * Contract tests for CreateShiftController, exercised over real HTTP.
 *
 * One scenario per externally observable contract: creation, malformed input,
 * validation, conflict and upstream outage. The rule matrix lives in the
 * functional suite.
 *
 * The tests assume the spawned test server: Nager is mocked and notifications
 * are enabled. Pointing E2E_BASE_URL at another server requires the same test
 * configuration.
 *
 * Run with:   composer e2e
 *             vendor/bin/phpunit tests/E2E --filter CreateShiftE2E
 * Against a running server instead of spawning one:
 *             E2E_BASE_URL=http://127.0.0.1:8000 composer e2e
 */
final class CreateShiftE2ETest extends AbstractApiTestCase
{
    public function testCreatesShift(): void
    {
        $employeeId = $this->uniqueEmployeeId();

        $response = $this->postShift(['employee_id' => $employeeId]);

        $body = $this->assertCreated($response);
        self::assertSame($employeeId, $body['employee_id']);
        self::assertSame('2026-12-23', $body['date']);
        self::assertSame('09:00', $body['start_time']);
        self::assertSame('17:00', $body['end_time']);
        self::assertSame('LT', $body['country_code']);

        $id = $body['id'] ?? null;
        self::assertIsString($id);

        self::assertStringContainsString('application/json', (string) $response->header('content-type'));
        self::assertStringContainsString($id, $this->sentNotificationLines());
    }

    public function testRejectsMalformedJson(): void
    {
        $response = $this->postRaw('/shifts', '{invalid');

        $this->assertErrorCode($response, 400, 'invalid_request');
    }

    public function testRejectsAnInvalidShift(): void
    {
        $response = $this->postShift(['start_time' => '17:00', 'end_time' => '09:00']);

        $this->assertErrorCode($response, 422, 'endTime');
    }

    public function testRejectsAnOverlappingShift(): void
    {
        $employeeId = $this->uniqueEmployeeId();

        self::assertSame(201, $this->postShift(['employee_id' => $employeeId])->status);

        $response = $this->postShift([
            'employee_id' => $employeeId,
            'start_time' => '10:00',
            'end_time' => '18:00',
        ]);

        $this->assertErrorCode($response, 409, 'shift_overlap');
    }

    public function testReportsAHolidayCalendarOutage(): void
    {
        $response = $this->postShift(['date' => '2099-01-02']);

        $this->assertErrorCode($response, 503, 'holiday_calendar');
    }

    /**
     * @param array<string, string> $overrides
     */
    private function postShift(array $overrides = []): HttpResponse
    {
        return $this->postJson('/shifts', $overrides + [
            'employee_id' => $this->uniqueEmployeeId(),
            'date' => '2026-12-23',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'country_code' => 'LT',
        ]);
    }
}
