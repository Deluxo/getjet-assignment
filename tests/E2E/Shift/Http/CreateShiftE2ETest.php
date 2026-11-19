<?php

declare(strict_types=1);

namespace App\Tests\E2E\Shift\Http;

use App\Tests\E2E\AbstractApiTestCase;
use App\Tests\E2E\Support\HttpResponse;

/**
 * End-to-end tests for CreateShiftController, exercised over real HTTP.
 *
 * Run with:   composer e2e
 *             vendor/bin/phpunit tests/E2E --filter CreateShiftE2E
 * Against a running server instead of spawning one:
 *             E2E_BASE_URL=http://127.0.0.1:8000 composer e2e
 */
final class CreateShiftE2ETest extends AbstractApiTestCase
{
    /**
     * Smoke test: the app is up and routing answers. Should stay green at all times.
     */
    public function testServerIsReachable(): void
    {
        self::assertSame(405, $this->get('/shifts')->status);
    }

    /**
     * Sample business case — red until the endpoint is implemented.
     */
    public function testCreatesShift(): void
    {
        $this->assertCreated($this->postShift());
    }

    private function postShift(): HttpResponse
    {
        return $this->postJson('/shifts', [
            'employee_id' => $this->uniqueEmployeeId(),
            'date' => '2026-10-06',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'country_code' => 'LT',
        ]);
    }
}
