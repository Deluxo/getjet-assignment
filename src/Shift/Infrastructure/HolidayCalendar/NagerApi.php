<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\HolidayCalendar;

use App\Shared\Debug;
use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use App\Shift\Domain\Port\Out\HolidayCalendar;
use Psr\Cache\CacheException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class NagerApi implements HolidayCalendar
{
    private const int CACHE_TTL_SECONDS = 604800;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return TaskEither<Error, bool>
     */
    public function isPublicHoliday(string $countryCode, \DateTimeImmutable $date): TaskEither
    {
        return TaskEither::defer(function () use ($countryCode, $date): Either {
            $country = strtoupper($countryCode);
            $year = (int) $date->format('Y');
            $target = $date->format('Y-m-d');
            $key = self::cacheKey($country, $year);

            $dates = $this->read($key);

            if (null !== $dates) {
                return Either::Right(in_array($target, $dates, true));
            }

            return $this->fetch($country, $year)->fold(
                static fn(Error $error): Either => Either::Left($error),
                function (array $dates) use ($key, $target): Either {
                    $this->write($key, $dates);

                    return Either::Right(in_array($target, $dates, true));
                },
            );
        });
    }

    /**
     * @return list<string>|null
     */
    private function read(string $key): ?array
    {
        try {
            $item = $this->cache->getItem($key);

            if (!$item->isHit()) {
                return null;
            }

            $value = $item->get();
        } catch (CacheException $exception) {
            $this->logger->warning('Could not read the public holiday cache.', ['exception' => $exception]);

            return null;
        }

        if (!is_array($value) || !array_is_list($value)) {
            return null;
        }

        $dates = [];

        foreach ($value as $date) {
            if (!is_string($date)) {
                return null;
            }

            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * @param list<string> $dates
     */
    private function write(string $key, array $dates): void
    {
        try {
            $item = $this->cache->getItem($key);
            $item->set($dates);
            $item->expiresAfter(self::CACHE_TTL_SECONDS);
            $this->cache->save($item);
        } catch (CacheException $exception) {
            $this->logger->warning('Could not write the public holiday cache.', ['exception' => $exception]);
        }
    }

    /**
     * @return Either<Error, list<string>>
     */
    private function fetch(string $country, int $year): Either
    {
        try {
            $response = $this->httpClient->request('GET', sprintf('Holidays/%s/%d', $country, $year));
            $status = $response->getStatusCode();

            if (200 !== $status) {
                $this->logger->warning('The public holiday calendar returned an unexpected status.', [
                    'country' => $country,
                    'year' => $year,
                    'status' => $status,
                ]);

                return Either::Left(new Error(
                    'holiday_calendar',
                    sprintf('The public holiday calendar is unavailable (HTTP %d).', $status),
                ));
            }

            $body = $response->getContent(false);
        } catch (HttpClientException $exception) {
            $this->logger->warning('Could not fetch public holidays.', [
                'country' => $country,
                'year' => $year,
                'exception' => $exception,
            ]);

            return Either::Left(new Error(
                'holiday_calendar',
                'The public holiday calendar is unavailable.',
            ));
        }

        $payload = json_decode($body, false, 512);

        if (JSON_ERROR_NONE !== json_last_error()) {
            $this->logger->warning('The public holiday calendar returned invalid JSON.', [
                'country' => $country,
                'year' => $year,
            ]);

            return Either::Left(new Error(
                'holiday_calendar',
                'The public holiday calendar is unavailable (invalid response).',
            ));
        }

        return $this->holidayDates($payload, $country, $year);
    }

    /**
     * @return Either<Error, list<string>>
     */
    private function holidayDates(mixed $payload, string $country, int $year): Either
    {
        if (!is_array($payload)) {
            return $this->unexpectedPayload($country, $year);
        }

        $dates = [];

        foreach ($payload as $entry) {
            if (!$entry instanceof \stdClass) {
                return $this->unexpectedPayload($country, $year);
            }

            $date = $entry->date ?? null;
            $nationalHoliday = $entry->nationalHoliday ?? null;
            $holidayTypes = $entry->holidayTypes ?? null;

            if (!is_string($date) || !is_bool($nationalHoliday) || !is_array($holidayTypes) || !array_is_list($holidayTypes)) {
                return $this->unexpectedPayload($country, $year);
            }

            foreach ($holidayTypes as $type) {
                if (!is_string($type)) {
                    return $this->unexpectedPayload($country, $year);
                }
            }

            if ($nationalHoliday && in_array('Public', $holidayTypes, true)) {
                $dates[] = $date;
            }
        }

        return Either::Right(array_values(array_unique($dates)));
    }

    /**
     * @return Either<Error, never>
     */
    private function unexpectedPayload(string $country, int $year): Either
    {
        $this->logger->warning('The public holiday calendar returned an unexpected payload.', [
            'country' => $country,
            'year' => $year,
        ]);

        return Either::Left(new Error(
            'holiday_calendar',
            'The public holiday calendar is unavailable (unexpected response).',
        ));
    }

    private static function cacheKey(string $country, int $year): string
    {
        return sprintf('holiday_calendar.%s.%d', $country, $year);
    }
}
