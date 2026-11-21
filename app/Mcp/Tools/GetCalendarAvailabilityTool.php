<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Models\CmsMcpCredential;
use App\Services\Availability\AvailabilityCalendar;
use App\Services\Availability\AvailabilityLookupFailed;
use App\Services\Availability\AvailabilityOutOfRange;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetCalendarAvailabilityTool extends CmsTool
{
    protected string $name = 'get_calendar_availability';

    protected string $description = 'Read the public availability status of each day in one month (open, limited, held, booked, or unavailable) using the same calendar as the public booking page. Returns no client names. Read only.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly AvailabilityCalendar $calendar,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::JOBS_READ;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, [
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        if (! $this->calendar->isReady()) {
            throw ValidationException::withMessages(['month' => 'Availability is not configured.']);
        }

        try {
            $payload = $this->calendar->forMonth($validated['month']);
        } catch (AvailabilityOutOfRange) {
            throw ValidationException::withMessages(['month' => 'That month is outside the booking window.']);
        } catch (AvailabilityLookupFailed) {
            throw ValidationException::withMessages(['month' => 'Availability could not be checked.']);
        }

        return Response::structured($payload);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'month' => $schema->string()->description('Month as YYYY-MM.')->required(),
        ];
    }
}
