<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\Http\Controller;

use App\Shift\Domain\Port\In\CreateShift;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateShiftController
{
    public function __construct(private readonly CreateShift $createShift)
    {
    }

    #[Route('/shifts', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        try {
            $payload = $request->toArray();
        } catch (\JsonException) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (!\is_array($payload)) {
            return new JsonResponse(['error' => 'Payload must be a JSON object'], Response::HTTP_BAD_REQUEST);
        }

        $required = ['employee_id', 'date', 'start_time', 'end_time', 'country_code'];

        foreach ($required as $field) {
            if (!isset($payload[$field]) || !\is_string($payload[$field])) {
                return new JsonResponse(
                    ['error' => sprintf('Missing or invalid field "%s".', $field)],
                    Response::HTTP_BAD_REQUEST,
                );
            }
        }

        try {
            $shiftId = $this->createShift->create(
                (string) $payload['employee_id'],
                (string) $payload['date'],
                (string) $payload['start_time'],
                (string) $payload['end_time'],
                (string) $payload['country_code'],
            );
        } catch (\LogicException) {
            return new JsonResponse(['error' => 'Not implemented'], Response::HTTP_NOT_IMPLEMENTED);
        }

        return new JsonResponse(
            ['id' => $shiftId],
            Response::HTTP_CREATED,
        );
    }
}
