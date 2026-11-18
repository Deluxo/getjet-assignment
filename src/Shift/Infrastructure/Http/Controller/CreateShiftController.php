<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\Http\Controller;

use App\Shared\Infrastructure\Api\Json\JsonApiTrait;
use App\Shared\Infrastructure\Api\Json\ToJson;
use App\Shift\Application\NotImplementedError;
use App\Shift\Domain\Port\In\CreateShift;
use App\Shift\Domain\Port\Out\ShiftErrors;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateShiftController
{
    use JsonApiTrait;

    public function __construct(private readonly CreateShift $createShift)
    {
    }

    #[Route('/shifts', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        return $this->parseJsonPayload($request)->fold(
            onLeft: static fn (string $message): JsonResponse => new JsonResponse(['error' => $message], Response::HTTP_BAD_REQUEST),
            onRight: function (array $payload): Response {
                $fields = [
                    'employee_id' => (string) ($payload['employee_id'] ?? ''),
                    'date' => (string) ($payload['date'] ?? ''),
                    'start_time' => (string) ($payload['start_time'] ?? ''),
                    'end_time' => (string) ($payload['end_time'] ?? ''),
                    'country_code' => (string) ($payload['country_code'] ?? ''),
                ];

                return $this->taskToJsonResponse(
                    $this->createShift->create(
                        $fields['employee_id'],
                        $fields['date'],
                        $fields['start_time'],
                        $fields['end_time'],
                        $fields['country_code'],
                    ),
                    onLeft: static function (ToJson $left): JsonResponse {
                        if ($left instanceof ShiftErrors && $left->errors() !== [] && $left->errors()[0] instanceof NotImplementedError) {
                            return new JsonResponse(['error' => 'Not implemented'], Response::HTTP_NOT_IMPLEMENTED);
                        }

                        return new JsonResponse($left->toJson(), Response::HTTP_BAD_REQUEST);
                    },
                );
            },
        );
    }
}
