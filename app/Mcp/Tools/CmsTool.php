<?php

namespace App\Mcp\Tools;

use App\Mcp\CmsMcpGate;
use App\Mcp\Tools\Concerns\RegistersForAbility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * Base for the management tools. A tool is listed only when the credential
 * holds its ability, and handle() re-checks the ability and writes the audit
 * log line before any data is read or changed.
 */
abstract class CmsTool extends Tool
{
    use RegistersForAbility;

    public function __construct(protected readonly CmsMcpGate $gate) {}

    abstract protected function ability(): string;

    abstract protected function run(Request $request): ResponseFactory;

    public function shouldRegister(): bool
    {
        return $this->credentialAllows($this->ability());
    }

    public function handle(Request $request): ResponseFactory
    {
        $this->gate->authorize($this->ability(), $this->name);

        return $this->run($request);
    }

    /**
     * Validate the tool arguments with rules shared with the admin screens.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    protected function validated(Request $request, array $rules, array $defaults = []): array
    {
        return Validator::make([...$defaults, ...$request->all()], $rules)->validate();
    }

    /**
     * Make every rule optional so a tool can change one field at a time.
     *
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function partial(array $rules): array
    {
        return array_map(fn (mixed $rule): array => ['sometimes', ...(array) $rule], $rules);
    }

    /**
     * @return array<string, mixed>
     */
    protected function listRules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $validated
     * @param  callable(mixed): array<string, mixed>  $map
     */
    protected function listing(Builder $query, array $validated, callable $map, string $key): ResponseFactory
    {
        $paginator = $query->paginate((int) ($validated['limit'] ?? 25), ['*'], 'page', (int) ($validated['page'] ?? 1));

        return Response::structured([
            $key => $paginator->getCollection()->map($map)->values()->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }

    protected function notFound(string $what): never
    {
        throw ValidationException::withMessages([$what => ucfirst($what).' was not found.']);
    }

    protected function mustBeDraft(string $what, ?string $status): void
    {
        if ($status === 'draft') {
            return;
        }

        throw ValidationException::withMessages([
            $what => 'Only a draft can be changed here. Publishing is a separate operation.',
        ]);
    }
}
