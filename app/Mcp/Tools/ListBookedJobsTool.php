<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\CmsPayloads;
use App\Models\BookedJob;
use App\Models\CmsMcpCredential;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListBookedJobsTool extends CmsTool
{
    protected string $name = 'list_booked_jobs';

    protected string $description = 'List booked jobs for one month, plus upcoming jobs and jobs still awaiting a date. Calendar notes, coordinator notes, and calendar ids are not returned. Read only.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly CmsPayloads $payloads,
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
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'month' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $month = (int) ($validated['month'] ?? now()->month);
        $map = fn (BookedJob $job): array => $this->payloads->bookedJob($job);

        return Response::structured([
            'year' => $year,
            'month' => $month,
            'jobs' => BookedJob::query()->inMonth($year, $month)->orderBy('event_date')->get()->map($map)->values()->all(),
            'upcoming' => BookedJob::upcoming()->limit(5)->get()->map($map)->values()->all(),
            'awaiting_date' => BookedJob::awaitingDate()->latest('created_at')->limit(50)->get()->map($map)->values()->all(),
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'year' => $schema->integer()->description('Year. Defaults to the current year.')->min(2000)->max(2100),
            'month' => $schema->integer()->description('Month 1 to 12. Defaults to the current month.')->min(1)->max(12),
        ];
    }
}
