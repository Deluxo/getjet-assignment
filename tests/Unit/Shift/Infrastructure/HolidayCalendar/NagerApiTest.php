<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shift\Infrastructure\HolidayCalendar;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shift\Infrastructure\HolidayCalendar\NagerApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class NagerApiTest extends TestCase
{
    private const string BASE_URI = 'https://nagerholidays.com/api/v4/';

    public function testReturnsTrueForANationalPublicHoliday(): void
    {
        $client = new MockHttpClient(
            self::response([
                ['date' => '2026-10-06', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
            ]),
            self::BASE_URI,
        );

        $result = self::api($client)->isPublicHoliday('LT', self::date('2026-10-06'))->run();

        self::assertTrue(self::right($result));
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('nonHolidayEntries')]
    public function testReturnsFalseForEntriesThatAreNotPublicNationalHolidays(array $entry): void
    {
        $client = new MockHttpClient(self::response([$entry]), self::BASE_URI);

        $result = self::api($client)->isPublicHoliday('LT', self::date('2026-10-06'))->run();

        self::assertFalse(self::right($result));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function nonHolidayEntries(): iterable
    {
        yield 'public but not national' => [
            ['date' => '2026-10-06', 'nationalHoliday' => false, 'holidayTypes' => ['Public']],
        ];
        yield 'national but not public' => [
            ['date' => '2026-10-06', 'nationalHoliday' => true, 'holidayTypes' => ['Bank']],
        ];
        yield 'another date' => [
            ['date' => '2026-01-01', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
        ];
    }

    public function testReturnsAnErrorWhenTheCalendarRespondsWithANonSuccessStatus(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['http_code' => 503]), self::BASE_URI);

        $result = self::api($client)->isPublicHoliday('LT', self::date('2026-10-06'))->run();

        self::assertSame('holiday_calendar', self::left($result)->code());
    }

    public function testReturnsAnErrorForInvalidJson(): void
    {
        $client = new MockHttpClient(new MockResponse('{invalid', ['http_code' => 200]), self::BASE_URI);

        $result = self::api($client)->isPublicHoliday('LT', self::date('2026-10-06'))->run();

        self::assertSame('holiday_calendar', self::left($result)->code());
    }

    #[DataProvider('unexpectedPayloads')]
    public function testReturnsAnErrorForUnexpectedPayloads(string $body): void
    {
        $client = new MockHttpClient(new MockResponse($body, ['http_code' => 200]), self::BASE_URI);

        $result = self::api($client)->isPublicHoliday('LT', self::date('2026-10-06'))->run();

        self::assertSame('holiday_calendar', self::left($result)->code());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unexpectedPayloads(): iterable
    {
        yield 'top-level object' => ['{}'];
        yield 'entry is not an object' => ['[1]'];
        yield 'missing date' => ['[{"nationalHoliday":true,"holidayTypes":["Public"]}]'];
        yield 'missing nationalHoliday' => ['[{"date":"2026-10-06","holidayTypes":["Public"]}]'];
        yield 'missing holidayTypes' => ['[{"date":"2026-10-06","nationalHoliday":true}]'];
        yield 'nationalHoliday is not a bool' => ['[{"date":"2026-10-06","nationalHoliday":"yes","holidayTypes":["Public"]}]'];
        yield 'holidayTypes is not an array' => ['[{"date":"2026-10-06","nationalHoliday":true,"holidayTypes":"Public"}]'];
        yield 'holidayTypes contains a non-string' => ['[{"date":"2026-10-06","nationalHoliday":true,"holidayTypes":[1]}]'];
    }

    public function testReturnsAnErrorWhenTheTransportFails(): void
    {
        $client = new MockHttpClient(
            static fn (): never => throw new TransportException('Connection timed out.'),
            self::BASE_URI,
        );

        $result = self::api($client)->isPublicHoliday('LT', self::date('2026-10-06'))->run();

        self::assertSame('holiday_calendar', self::left($result)->code());
    }

    public function testRequestsTheHolidayCalendarForTheCountryAndYear(): void
    {
        $requestedUrl = null;

        $client = new MockHttpClient(
            static function (string $method, string $url) use (&$requestedUrl): MockResponse {
                $requestedUrl = $url;

                return new MockResponse('[]');
            },
            self::BASE_URI,
        );

        self::api($client)->isPublicHoliday('lt', self::date('2026-10-06'))->run();

        self::assertSame('https://nagerholidays.com/api/v4/Holidays/LT/2026', $requestedUrl);
    }

    public function testUsesTheCacheForAnotherDateInTheSameYear(): void
    {
        $client = new MockHttpClient(
            self::response([
                ['date' => '2026-01-01', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
            ]),
            self::BASE_URI,
        );
        $api = self::api($client);

        self::assertTrue(self::right($api->isPublicHoliday('LT', self::date('2026-01-01'))->run()));
        self::assertFalse(self::right($api->isPublicHoliday('LT', self::date('2026-01-02'))->run()));

        self::assertSame(1, $client->getRequestsCount());
    }

    public function testFetchesEachYearSeparately(): void
    {
        $client = new MockHttpClient(
            [
                self::response([
                    ['date' => '2026-01-01', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
                ]),
                self::response([
                    ['date' => '2027-01-01', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
                ]),
            ],
            self::BASE_URI,
        );
        $api = self::api($client);

        self::assertTrue(self::right($api->isPublicHoliday('LT', self::date('2026-01-01'))->run()));
        self::assertTrue(self::right($api->isPublicHoliday('LT', self::date('2027-01-01'))->run()));

        self::assertSame(2, $client->getRequestsCount());
    }

    public function testDoesNotCacheAFailedFetch(): void
    {
        $client = new MockHttpClient(
            [
                new MockResponse('', ['http_code' => 503]),
                self::response([
                    ['date' => '2026-01-01', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
                ]),
            ],
            self::BASE_URI,
        );
        $api = self::api($client);

        self::assertSame(
            'holiday_calendar',
            self::left($api->isPublicHoliday('LT', self::date('2026-01-01'))->run())->code(),
        );
        self::assertTrue(self::right($api->isPublicHoliday('LT', self::date('2026-01-01'))->run()));

        self::assertSame(2, $client->getRequestsCount());
    }

    public function testOverwritesACorruptCacheEntry(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('holiday_calendar.LT.2026');
        $item->set('not-a-list');
        $cache->save($item);

        $client = new MockHttpClient(
            self::response([
                ['date' => '2026-01-01', 'nationalHoliday' => true, 'holidayTypes' => ['Public']],
            ]),
            self::BASE_URI,
        );

        $result = self::api($client, $cache)->isPublicHoliday('LT', self::date('2026-01-01'))->run();

        self::assertTrue(self::right($result));
        self::assertSame(1, $client->getRequestsCount());
    }

    public function testDoesNotContactTheCalendarUntilTheTaskIsRun(): void
    {
        $client = new MockHttpClient(self::response([]), self::BASE_URI);
        $task = self::api($client)->isPublicHoliday('LT', self::date('2026-10-06'));

        self::assertSame(0, $client->getRequestsCount());

        $task->run();

        self::assertSame(1, $client->getRequestsCount());
    }

    private static function api(MockHttpClient $client, ?CacheItemPoolInterface $cache = null): NagerApi
    {
        return new NagerApi($client, $cache ?? new ArrayAdapter(), new NullLogger());
    }

    /**
     * @param list<array<string, mixed>> $entries
     */
    private static function response(array $entries, int $status = 200): MockResponse
    {
        return new MockResponse(
            json_encode($entries, JSON_THROW_ON_ERROR),
            ['http_code' => $status],
        );
    }

    private static function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value);
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     *
     * @return Error
     */
    private static function left(Either $either): mixed
    {
        return $either->fold(
            static fn (mixed $error): mixed => $error,
            static fn (): never => throw new \LogicException('Expected a left value.'),
        );
    }

    /**
     * @template T
     *
     * @param Either<Error, T> $either
     *
     * @return T
     */
    private static function right(Either $either): mixed
    {
        return $either->fold(
            static fn (): never => throw new \LogicException('Expected a right value.'),
            static fn (mixed $value): mixed => $value,
        );
    }
}
