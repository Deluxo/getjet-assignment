<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Api\Json;

use App\Shared\Error;
use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
use App\Shared\Infrastructure\Api\Json\JsonApiTrait;
use App\Shared\Infrastructure\Api\Json\ToJson;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class JsonApiTraitTest extends TestCase
{
    private JsonApiTraitHost $host;

    protected function setUp(): void
    {
        $this->host = new JsonApiTraitHost();
    }

    public function testParseReturnsLeftForEmptyPayload(): void
    {
        $result = $this->host->parse($this->request(''));

        self::assertSame('Payload is required.', $this->leftValue($result));
    }

    public function testParseReturnsLeftForInvalidJson(): void
    {
        $result = $this->host->parse($this->request('{invalid'));

        self::assertSame('Invalid JSON.', $this->leftValue($result));
    }

    public function testParseReturnsLeftForJsonArrayPayload(): void
    {
        $result = $this->host->parse($this->request('[1, 2]'));

        self::assertSame('Payload must be a JSON object.', $this->leftValue($result));
    }

    public function testParseReturnsRightForJsonObjectPayload(): void
    {
        $result = $this->host->parse($this->request('{"employee_id": "employee-123"}'));

        self::assertTrue($result->isRight());
        self::assertSame(
            ['employee_id' => 'employee-123'],
            $result->fold(
                onLeft: static fn(): array => [],
                onRight: static fn(array $payload): array => $payload,
            ),
        );
    }

    public function testUnwrapReturnsCreatedForRight(): void
    {
        $task = TaskEither::right(new RightValue());

        $response = $this->host->unwrap($task);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame(['id' => 'shift-1'], json_decode((string) $response->getContent(), true));
    }

    public function testUnwrapReturnsBadRequestForLeftByDefault(): void
    {
        /** @var list<Error> $errors */
        $errors = [new Error('boom', 'Boom.')];
        $task = TaskEither::left($errors);

        $response = $this->host->unwrap($task);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(
            ['errors' => [['code' => 'boom', 'message' => 'Boom.']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testUnwrapEncodesStringLeftAsInvalidRequest(): void
    {
        $task = TaskEither::left('Invalid JSON.');

        $response = $this->host->unwrap($task);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(
            ['errors' => [['code' => 'invalid_request', 'message' => 'Invalid JSON.']]],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testUnwrapDelegatesLeftToProvidedHook(): void
    {
        /** @var list<Error> $errors */
        $errors = [new Error('boom', 'Boom.')];
        $task = TaskEither::left($errors);

        $response = $this->host->unwrap(
            $task,
            static fn(string|array $left): JsonResponse => new JsonResponse(['hook' => true], Response::HTTP_NOT_IMPLEMENTED),
        );

        self::assertSame(Response::HTTP_NOT_IMPLEMENTED, $response->getStatusCode());
        self::assertSame(['hook' => true], json_decode((string) $response->getContent(), true));
    }

    public function testParseAsHydratesMappedKeys(): void
    {
        $result = $this->host->parseAs(
            TestRequest::class,
            ['employee_id' => 'employeeId', 'date' => 'date'],
            $this->request('{"employee_id": "employee-123", "date": "2026-10-06"}'),
        );

        self::assertTrue($result->isRight());
        $request = $result->fold(
            onLeft: static fn(): null => null,
            onRight: static fn(TestRequest $value): TestRequest => $value,
        );

        self::assertSame('employee-123', $request->employeeId);
        self::assertSame('2026-10-06', $request->date);
    }

    public function testParseAsCoercesScalarValues(): void
    {
        $result = $this->host->parseAs(
            TestRequest::class,
            ['employee_id' => 'employeeId'],
            $this->request('{"employee_id": 123}'),
        );

        self::assertTrue($result->isRight());
        $request = $result->fold(
            onLeft: static fn(): null => null,
            onRight: static fn(TestRequest $value): TestRequest => $value,
        );

        self::assertSame('123', $request->employeeId);
    }

    public function testParseAsUsesConstructorDefaults(): void
    {
        $result = $this->host->parseAs(
            TestRequest::class,
            ['employee_id' => 'employeeId', 'date' => 'date'],
            $this->request('{"employee_id": "employee-123"}'),
        );

        self::assertTrue($result->isRight());
        $request = $result->fold(
            onLeft: static fn(): null => null,
            onRight: static fn(TestRequest $value): TestRequest => $value,
        );

        self::assertNull($request->date);
    }

    public function testParseAsReturnsLeftForMissingRequiredField(): void
    {
        $result = $this->host->parseAs(
            TestRequest::class,
            ['employee_id' => 'employeeId'],
            $this->request('{}'),
        );

        self::assertSame('Missing required fields.', $this->leftValue($result));
    }

    public function testParseAsReturnsLeftForInvalidFieldType(): void
    {
        $result = $this->host->parseAs(
            TestRequest::class,
            ['employee_id' => 'employeeId'],
            $this->request('{"employee_id": []}'),
        );

        self::assertSame('Invalid field type.', $this->leftValue($result));
    }

    /**
     * @param Either<string, array> $result
     */
    private function leftValue(Either $result): string
    {
        return $result->fold(
            onLeft: static fn(string $message): string => $message,
            onRight: static fn(): string => '',
        );
    }

    private function request(string $content): Request
    {
        return Request::create('/shifts', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $content);
    }
}

final class JsonApiTraitHost
{
    use JsonApiTrait;

    public function parse(Request $request): Either
    {
        return $this->parseJsonPayload($request);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<string, string> $map
     *
     * @return Either<string, T>
     */
    public function parseAs(string $class, array $map, Request $request): Either
    {
        return $this->parseJsonPayloadAs($class, $map, $request);
    }

    /**
     * @param TaskEither<string|list<Error>, ToJson> $task
     * @param (callable(string|list<Error>): JsonResponse)|null $onLeft
     */
    public function unwrap(TaskEither $task, ?callable $onLeft = null): JsonResponse
    {
        return $this->taskToJsonResponse($task, $onLeft);
    }
}

final class RightValue implements ToJson
{
    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return ['id' => 'shift-1'];
    }
}

final readonly class TestRequest
{
    public function __construct(
        public string $employeeId,
        public ?string $date = null,
    ) {}
}
