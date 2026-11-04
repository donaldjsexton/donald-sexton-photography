<?php

namespace App\Http\Controllers;

use App\Services\Availability\AvailabilityCalendar;
use App\Services\Availability\AvailabilityLookupFailed;
use App\Services\Availability\AvailabilityOutOfRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __invoke(Request $request, AvailabilityCalendar $calendar): JsonResponse
    {
        $month = $request->query('month');

        if (! is_string($month) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            return $this->error('Provide a month as YYYY-MM.', 422);
        }

        if (! $calendar->isReady()) {
            return $this->error('Availability is not configured.', 503);
        }

        try {
            $payload = $calendar->forMonth($month);
        } catch (AvailabilityOutOfRange) {
            return $this->error('That month is outside the booking window.', 422);
        } catch (AvailabilityLookupFailed) {
            return $this->error('Availability could not be checked.', 503);
        }

        $seconds = $calendar->cacheSeconds();

        return response()
            ->json($payload)
            ->header('Cache-Control', "public, max-age={$seconds}, s-maxage={$seconds}");
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()
            ->json(['message' => $message], $status)
            ->header('Cache-Control', 'private, no-store');
    }
}
