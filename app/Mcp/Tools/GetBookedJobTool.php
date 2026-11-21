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
class GetBookedJobTool extends CmsTool
{
    protected string $name = 'get_booked_job';

    protected string $description = 'Read one booked job by id. Calendar notes, coordinator notes, and calendar ids are not returned. Read only.';

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
        $validated = $this->validated($request, ['job_id' => ['required', 'integer', 'min:1']]);
        $job = BookedJob::query()->find((int) $validated['job_id']);

        if (! $job instanceof BookedJob) {
            $this->notFound('job');
        }

        return Response::structured(['job' => $this->payloads->bookedJob($job)]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'job_id' => $schema->integer()->description('Booked job id.')->required()->min(1),
        ];
    }
}
