<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Api\Json;

use App\Shared\Functional\Either;
use App\Shared\Functional\TaskEither;
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
     * Parses the request body and hydrates a class from it.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     * @param array<string, string> $map JSON key => constructor parameter name
     *
     * @return Either<string, T>
     */
    private function parseJsonPayloadAs(string $class, array $map, Request $request): Either
    {
        return $this->parseJsonPayload($request)->flatMap(static function (array $payload) use ($class, $map): Either {
            $arguments = [];

            foreach (array_intersect_key($map, $payload) as $jsonKey => $parameter) {
                $arguments[$parameter] = $payload[$jsonKey];
            }

            try {
                return Either::Right((new \ReflectionClass($class))->newInstanceArgs($arguments));
            } catch (\ArgumentCountError) {
                return Either::Left('Missing required fields.');
            } catch (\TypeError) {
                return Either::Left('Invalid field type.');
            }
        });
    }

    /**
     * Unwraps a task of an either into a JsonResponse: Right maps to 201 Created,
     * Left maps to 400 Bad Request unless an explicit onLeft hook is provided.
     *
     * @param TaskEither<ToJson, ToJson> $task
     */
    private function taskToJsonResponse(TaskEither $task): JsonResponse
    {
        return $task->fold(
            onLeft: $onLeft ?? static fn(ToJson $left): JsonResponse => new JsonResponse($left->toJson(), Response::HTTP_BAD_REQUEST),
            onRight: static fn(ToJson $right): JsonResponse => new JsonResponse($right->toJson(), Response::HTTP_CREATED),
        )->run();
    }
}
