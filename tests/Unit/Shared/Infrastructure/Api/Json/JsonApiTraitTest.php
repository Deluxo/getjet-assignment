<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Api\Json;

use App\Shared\Functional\Either;
use App\Shared\Functional\Task;
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
                onLeft: static fn (): array => [],
                onRight: static fn (array $payload): array => $payload,
            ),
        );
    }

    public function testUnwrapReturnsCreatedForRight(): void
    {
        $task = Task::of(static fn (): Either => Either::Right(new RightValue()));

        $response = $this->host->unwrap($task);

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        self::assertSame(['id' => 'shift-1'], json_decode((string) $response->getContent(), true));
    }

    public function testUnwrapReturnsBadRequestForLeftByDefault(): void
    {
        $task = Task::of(static fn (): Either => Either::Left(new LeftValue()));

        $response = $this->host->unwrap($task);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertSame(['errors' => ['boom']], json_decode((string) $response->getContent(), true));
    }

    public function testUnwrapDelegatesLeftToProvidedHook(): void
    {
        $task = Task::of(static fn (): Either => Either::Left(new LeftValue()));

        $response = $this->host->unwrap(
            $task,
            static fn (ToJson $left): JsonResponse => new JsonResponse(['hook' => true], Response::HTTP_NOT_IMPLEMENTED),
        );

        self::assertSame(Response::HTTP_NOT_IMPLEMENTED, $response->getStatusCode());
        self::assertSame(['hook' => true], json_decode((string) $response->getContent(), true));
    }

    /**
     * @param Either<string, array> $result
     */
    private function leftValue(Either $result): string
    {
        return $result->fold(
            onLeft: static fn (string $message): string => $message,
            onRight: static fn (): string => '',
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
     * @param Task<Either<ToJson, ToJson>> $task
     * @param callable(ToJson): JsonResponse|null $onLeft
     */
    public function unwrap(Task $task, ?callable $onLeft = null): JsonResponse
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

final class LeftValue implements ToJson
{
    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return ['errors' => ['boom']];
    }
}
