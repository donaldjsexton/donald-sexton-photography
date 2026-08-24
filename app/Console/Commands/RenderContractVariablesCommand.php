<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\Contracts\ContractVariableResolver;
use Illuminate\Console\Command;

class RenderContractVariablesCommand extends Command
{
    protected $signature = 'contracts:render-variables
        {--contract= : Limit to a single contract number or id}
        {--dry-run : Report what would change without saving}';

    protected $description = 'Resolve any "{{token}}" merge variables left unrendered in saved contract bodies.';

    public function handle(ContractVariableResolver $resolver): int
    {
        $query = Contract::query()->where('body', 'like', '%{{%');

        if ($contractKey = $this->option('contract')) {
            $query->where(function ($builder) use ($contractKey) {
                $builder->where('number', $contractKey)
                    ->orWhere('id', (int) $contractKey);
            });
        }

        $updated = 0;

        foreach ($query->cursor() as $contract) {
            $rendered = $resolver->renderForContract($contract);

            if ($rendered === $contract->body) {
                $this->line("{$contract->number}: no known tokens to resolve.");

                continue;
            }

            $updated++;
            $this->line("{$contract->number}: tokens resolved.");

            if (! $this->option('dry-run')) {
                $contract->update(['body' => $rendered]);
            }
        }

        $this->info($this->option('dry-run')
            ? "{$updated} contract(s) would be updated."
            : "{$updated} contract(s) updated.");

        return self::SUCCESS;
    }
}
