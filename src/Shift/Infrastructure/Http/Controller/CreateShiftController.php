<?php

declare(strict_types=1);

namespace App\Shift\Infrastructure\Http\Controller;

use App\Shared\Functional\TaskEither;
use App\Shared\Infrastructure\Api\Json\JsonApiTrait;
use App\Shift\Domain\Port\In\CreateShift;
use App\Shift\Domain\Port\In\CreateShiftRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CreateShiftController
{
    use JsonApiTrait;

    public function __construct(private readonly CreateShift $createShift) {}

    #[Route('/shifts', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        return $this->parseJsonPayloadAs(
            $request,
            CreateShiftRequest::class,
            [
                'employee_id' => 'employeeId',
                'date' => 'date',
                'start_time' => 'startTime',
                'end_time' => 'endTime',
                'country_code' => 'countryCode',
            ],
        )
        |> TaskEither::fromEither(...)
        |> (fn($taskEither) => $taskEither->flatMap($this->createShift->invoke(...)))
        |> $this->taskToJsonResponse(...);
    }
}
