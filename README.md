# Shift Management API

A small Symfony 8.1 / PHP 8.6 service that registers employee shifts. It
validates eligibility (working hours, overlaps, Lithuanian public holidays),
persists the shift in SQLite, and records a notification about the created
shift. Notifications are an event subscriber, so the module can be disabled or
removed without touching the shift code.

## Quick start

The only prerequisites are Docker (with Compose) and network access to the
Nager holiday API at request time.

```sh
docker compose up --build
```

The API listens on <http://localhost:8000>. The container entrypoint applies
Doctrine migrations before the server starts, so a fresh checkout needs no
manual database step. Without Compose:

```sh
docker build -t shift-api:dev .
docker run --rm -p 8000:8000 shift-api:dev
```

Running directly on the host (PHP 8.6 required, see `composer.json`):

```sh
composer install
php bin/console doctrine:migrations:migrate --no-interaction
symfony serve -d            # or: php -S 127.0.0.1:8000 -t public
```

The SQLite database lives at `var/data.db` (`DATABASE_URL` in `.env`).

### Configuration

| Variable | Default | Purpose |
|---|---|---|
| `DATABASE_URL` | `sqlite:///%kernel.project_dir%/var/data.db` | Doctrine connection |
| `NAGER_API_BASE_URL` | `https://nagerholidays.com/api/v4/` | Public holiday source |
| `SHIFT_NOTIFICATIONS_ENABLED` | `1` | `0` disables recording and sending notifications |

Real secrets belong in `.env.local` (git-ignored) or the secrets vault.

## API

### Create a shift

```http
POST /shifts
Content-Type: application/json

{
  "employee_id": "employee-123",
  "date": "2026-10-06",
  "start_time": "09:00",
  "end_time": "17:00",
  "country_code": "LT"
}
```

`201 Created`:

```json
{
  "id": "01a1f911-e55f-79d9-bbf4-5c2751de2aa2",
  "employee_id": "employee-123",
  "date": "2026-10-06",
  "start_time": "09:00",
  "end_time": "17:00",
  "country_code": "LT"
}
```

Errors use one shape:

```json
{"errors": [{"code": "startTime", "message": "Field \"startTime\" must be after the start time."}]}
```

| Case | Status | Error code |
|---|---|---|
| Malformed/empty/non-object JSON, missing or wrongly typed fields | 400 | `invalid_request` |
| Empty employee id, unsupported country, invalid date/time, end before start, longer than 12 h, public holiday | 422 | field name |
| Overlapping shift for the same employee | 409 | `shift_overlap` |
| Holiday API unavailable / unexpected response | 503 | `holiday_calendar` |
| Database or notification storage failure | 500 | `shift_repository` / `notification_store` |

The status is derived from the first error's code in
`JsonApiTrait::statusFor()`; unknown codes fall back to 422 because every other
code currently comes from a field-level rule.

### Business rules

- All fields required; `employee_id` is a non-empty string.
- `date` is `YYYY-MM-DD`, times are `HH:mm` (24 h, zero padded).
- A shift starts and ends on the same day; the end must be after the start.
- Maximum duration is 12 hours.
- Shifts of one employee must not overlap; a shift may start exactly when
  another ends. The check and the insert are one SQL statement, so concurrent
  requests cannot both pass.
- Only `LT` is supported; the country code is normalized to upper case.
- Lithuanian public holidays are rejected. A day is a holiday when the Nager
  entry has `nationalHoliday: true` and `holidayTypes` contains `"Public"`.
  Responses are cached per country and year for seven days; transport errors,
  timeouts, non-200 responses, invalid JSON and unexpected payloads are all
  handled and surfaced as an error instead of a crash.
- Times are local wall-clock values; no timezone or DST handling (as specified).

## Notifications

Flow:

```
CreateShiftHandler
  └─ ShiftRepository::create()            INSERT shifts            (committed)
  └─ ShiftEvents::shiftPersisted()        Symfony event
        └─ Notification\OnShiftPersisted
              └─ RecordNotification
                    ├─ NotificationStore::record()   idempotent INSERT
                    └─ SendNotification              one attempt
```

- The notification identity is a SHA-256 hash of its content (version prefix,
  shift id, employee id, date, start, end). It is the primary key, so delivering
  the same event again inserts and sends nothing.
- The row is written **before** the send I/O. A failed send increments
  `attempts`, stores `last_error`, and leaves `sent_at` NULL for the retry.
- Notifications are delivered to `var/notifications/sent.ndjson` (one JSON
  object per line, append with `LOCK_EX`).
- Listener failures never fail shift creation: the publish boundary logs them.
  A notification that cannot be persisted at all is therefore lost (logged);
  the shift is already committed and asking the client to retry would produce
  an overlap error.
- Disable with `SHIFT_NOTIFICATIONS_ENABLED=0`. Removing `src/Notification` and
  its `#[AsEventListener]` subscriber deletes the feature; the shift module
  only knows the `ShiftEvents` port and its own event.

### Retrying failed sends

The request path tries once. A cron entry drains the rest:

```cron
* * * * * cd /app && php bin/console app:notifications:send --limit=100 >> var/log/notifications-cron.log 2>&1
```

| Option | Default | Meaning |
|---|---|---|
| `--limit` | 100 | Maximum notifications per run |
| `--max-attempts` | 5 | Rows that failed this often are skipped (kept for inspection) |
| `--older-than` | 60 | Seconds of grace so cron cannot race the in-flight first attempt |

The command exits non-zero when an attempt failed, so cron/monitoring notices.
Run one cron entry at a time: there is deliberately no distributed lock.
Delivery is at-least-once overall — a crash between a successful file append and
`markSent` resends on the next run; notification *creation* is exactly-once.

## Architecture

```
src/
  Shift/
    Domain/Port/In/          CreateShift, CreateShiftRequest, CreateShiftCommand
    Domain/Port/Out/         Shift, ShiftRepository, HolidayCalendar, ShiftEvents
    Application/             CreateShiftHandler, Rule/*
    Infrastructure/
      Http/Controller/       CreateShiftController
      Persistence/Doctrine/  DoctrineShiftRepository, ShiftEntity
      HolidayCalendar/       NagerApi
      Event/Emitter/         ShiftPersisted, SymfonyShiftEvents
  Notification/
    Domain/                  ShiftNotification, ports
    Application/             RecordNotification, SendNotification,
                             SendPendingNotifications
    Infrastructure/
      Event/Subscriber/      OnShiftPersisted
      Persistence/Doctrine/  DoctrineNotificationStore
      Sender/                FileNotificationSender
      Console/               SendPendingNotificationsCommand
  Shared/
    Functional/              Either, TaskEither, Maybe, Task, laws
    Validation/              ValidationRule, Validator
    Infrastructure/          JSON API trait, debug/console helpers
```

Key decisions:

- **Hexagonal, functional core.** Use cases are framework-free and return
  `TaskEither<list<Error>, T>`; errors are data, not exceptions. Adapters are
  the only place with IO. This is what makes the handler unit-testable with
  fakes and no kernel.
- **Validation rules are values.** `Validator::loadRules(...)->validateInto()`
  builds a lazy chain of `ValidationRule`s; each rule short-circuits the next,
  and the terminal promotion produces a `CreateShiftCommand` whose parsed fields
  are non-null. Adding a rule is a new class plus one argument, not an edit to a
  god method.
- **Overlap is a database invariant.** SQLite has no exclusion constraints, so
  the interval check and the insert are a single `INSERT … WHERE NOT EXISTS`,
  with a retry on "database is locked". A read-then-write implementation would
  race.
- **Notifications are a separate module behind an event.** The shift module
  publishes; the notification module subscribes. No shared transaction, no
  notification imports in the shift use case.
- **Idempotency is content-addressed.** The primary key is derived from the
  notification content instead of a random event id, so duplicate delivery is
  harmless by construction and needs no in-memory state.
- **Persist first, send second, retry by cron.** A failed send never loses the
  notification; a poison row is bounded by `attempts` instead of a dead-letter
  table.

## Tests

```sh
composer test     # unit + functional suites
composer e2e      # boots the app with php -S over real HTTP
composer analyse  # PHPStan, level max
composer check    # test + analyse
```

The suite covers the rules and the handler, the SQLite overlap/repository
behaviour, the Nager adapter (mocked HTTP client, no internet), notification
identity and idempotency, send/retry bookkeeping, the cron command, and the
HTTP flow including duplicate event delivery and a simulated holiday-API outage.

Tests need no internet: the holiday client is replaced by
`App\Tests\Support\NagerApiMockResponseFactory` in the `test` environment and
the filesystem cache uses the array adapter. `tests/bootstrap.php` recreates
`var/test.db` and applies migrations before the suite.

PHPStan runs at level max. A small scoped baseline
(`phpstan-baseline.neon`) covers the invariant-generic limitations of the
hand-rolled `Maybe`/`Either`/`TaskEither` primitives and their law tests; all
application, infrastructure and remaining test code is checked without
suppressions.

## Time spent

Approximately 6 hours: the validation/persistence core and the functional
primitives took the larger part, the notifications module (event, idempotency,
outbox, cron) about two hours, and Docker/README another one. It is an estimate
over several sessions, not a stopwatch.

## Known trade-offs and limitations

- The holiday API is fail-closed: if Nager is down, shifts cannot be created.
  For a production system I would cache last-known-good data per year, add a
  circuit breaker, and decide explicitly whether to fail open.
- Notifications cannot be persisted if SQLite fails, and the error is only
  logged. The only strict alternative is an outbox written in the shift
  transaction; that couples the modules and was deliberately avoided here.
- At-least-once sends (see above), no lock for overlapping cron runs, and rows
  that hit `--max-attempts` stay pending until an operator intervenes.
- SQLite-specific SQL (`ON CONFLICT`, `CURRENT_TIMESTAMP`, single-writer
  serialization) and `TIME`/`DATE` columns storing wall-clock values.
- No authentication, employee directory, shift listing/update/cancel, or UI —
  out of scope for the assignment, and the shift table contains everything a
  labour-cost module would need later.
- The scoped PHPStan baseline covers only the functional primitives; removing
  it would require reworking their generics (covariance, typed wrappers).

## With more time (production readiness)

- Return RFC problem details instead of the custom error envelope.
- Move to PostgreSQL with an exclusion constraint (`tstzrange`) for overlaps
  and a transactional outbox for notifications; replace the cron with Messenger
  (retry, backoff, dead-letter, metrics).
- Use a real sender with provider-side idempotency keys and delivery receipts.
- Add authentication/authorization, employee validation against a directory,
  and endpoints to query/cancel shifts.
- Add a clock abstraction (the domain currently only stores wall-clock dates and
  times), structured logs/metrics/tracing, CI (tests, static analysis, coding
  standard), and migration checks.
