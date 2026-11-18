<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Api\Json;

use App\Shared\Functional\Either;
use App\Shared\Functional\Task;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait JsonApiTrait
{
    /**
     * Parses the request body as a JSON object.
     *
     * @return Either<string, array> Left is a human-readable error message, Right is the decoded object.
     */
    private function parseJsonPayload(Request $request): Either
    {
        $content = $request->getContent();

        if ($content === '') {
            return Either::Left('Payload is required.');
        }

        try {
            $decoded = json_decode($content, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Either::Left('Invalid JSON.');
        }

        if (!\is_object($decoded)) {
            return Either::Left('Payload must be a JSON object.');
        }

        return Either::Right((array) $decoded);
    }

    /**
     * Unwraps a task of an either into a JsonResponse: Right maps to 201 Created,
     * Left maps to 400 Bad Request unless an explicit onLeft hook is provided.
     *
     * @param Task<Either<ToJson, ToJson>> $task
     * @param callable(ToJson): JsonResponse|null $onLeft
     */
    private function taskToJsonResponse(Task $task, ?callable $onLeft = null): JsonResponse
    {
        return $task->run()->fold(
            onRight: static fn (ToJson $right): JsonResponse => new JsonResponse($right->toJson(), Response::HTTP_CREATED),
            onLeft: $onLeft ?? static fn (ToJson $left): JsonResponse => new JsonResponse($left->toJson(), Response::HTTP_BAD_REQUEST),
        );
    }
}
