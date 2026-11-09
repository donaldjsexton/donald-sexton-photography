<?php

namespace App\Console\Commands;

use App\Models\CmsMcpCredential;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cms-mcp:revoke {credential : The numeric credential id}')]
#[Description('Revoke a CMS MCP credential. Later requests with its token are rejected.')]
class RevokeCmsMcpCredentialCommand extends Command
{
    public function handle(): int
    {
        $credential = CmsMcpCredential::query()->find((int) $this->argument('credential'));

        if (! $credential instanceof CmsMcpCredential) {
            $this->error('Credential was not found.');

            return self::FAILURE;
        }

        if ($credential->revoked_at === null) {
            $credential->forceFill(['revoked_at' => now()])->save();
        }

        $this->info('Credential '.$credential->id.' ('.$credential->name.') is revoked.');

        return self::SUCCESS;
    }
}
