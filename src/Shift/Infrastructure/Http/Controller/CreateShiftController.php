<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\Http\Controller;

use App\Shared\Error;
use App\Shift\Application\NotImplementedError;
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

        $fields = [
            'employee_id' => (string) ($payload['employee_id'] ?? ''),
            'date' => (string) ($payload['date'] ?? ''),
            'start_time' => (string) ($payload['start_time'] ?? ''),
            'end_time' => (string) ($payload['end_time'] ?? ''),
            'country_code' => (string) ($payload['country_code'] ?? ''),
        ];

        $result = $this->createShift->create(
            $fields['employee_id'],
            $fields['date'],
            $fields['start_time'],
            $fields['end_time'],
            $fields['country_code'],
        )->run();

        return $result->fold(
            onRight: static fn (string $shiftId): JsonResponse => new JsonResponse(['id' => $shiftId], Response::HTTP_CREATED),
            onLeft: static function (array $errors): JsonResponse {
                if ($errors !== [] && $errors[0] instanceof NotImplementedError) {
                    return new JsonResponse(['error' => 'Not implemented'], Response::HTTP_NOT_IMPLEMENTED);
                }

                $messages = array_map(
                    static fn (Error $e): string => $e->message(),
                    $errors,
                );

                return new JsonResponse(['errors' => $messages], Response::HTTP_BAD_REQUEST);
            },
        );
    }
}
