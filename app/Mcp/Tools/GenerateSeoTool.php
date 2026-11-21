<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Models\CmsMcpCredential;
use App\Services\Cms\SeoBackfill;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class GenerateSeoTool extends CmsTool
{
    protected string $name = 'generate_seo';

    protected string $description = 'Generate an SEO title and description for one draft page, draft journal post, draft story, or a venue with the same generators as the seo:generate commands. Fills blank fields only unless overwrite is true. Calls the Anthropic API.';

    public function __construct(
        CmsMcpGate $gate,
        private readonly SeoBackfill $seo,
    ) {
        parent::__construct($gate);
    }

    protected function ability(): string
    {
        return CmsMcpCredential::PAGES_WRITE;
    }

    /**
     * Listed when the credential can write any content type this tool covers.
     * The type-specific ability is checked again in run().
     */
    public function shouldRegister(): bool
    {
        foreach ([CmsMcpCredential::PAGES_WRITE, CmsMcpCredential::JOURNAL_WRITE, CmsMcpCredential::VENUES_WRITE, CmsMcpCredential::DRAFT] as $ability) {
            if ($this->credentialAllows($ability)) {
                return true;
            }
        }

        return false;
    }

    protected function run(Request $request): ResponseFactory
    {
        $validated = $this->validated($request, [
            'type' => ['required', Rule::in(SeoBackfill::types())],
            'id' => ['required', 'integer', 'min:1'],
            'overwrite' => ['sometimes', 'boolean'],
        ]);

        $required = match ($validated['type']) {
            'page' => CmsMcpCredential::PAGES_WRITE,
            'journal_post' => CmsMcpCredential::JOURNAL_WRITE,
            'venue' => CmsMcpCredential::VENUES_WRITE,
            'wedding_story' => CmsMcpCredential::DRAFT,
        };

        $this->gate->authorize($required, $this->name);

        $model = SeoBackfill::modelFor($validated['type'])::query()->find((int) $validated['id']);

        if (! $model instanceof Model) {
            $this->notFound($validated['type']);
        }

        if ($validated['type'] !== 'venue') {
            $this->mustBeDraft($validated['type'], $model->status);
        }

        return Response::structured($this->seo->run($model, (bool) ($validated['overwrite'] ?? false)));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('page, journal_post, venue, or wedding_story.')->required(),
            'id' => $schema->integer()->description('Record id.')->required()->min(1),
            'overwrite' => $schema->boolean()->description('Replace existing SEO fields. Defaults to false.'),
        ];
    }
}
