<?php

namespace App\Console\Commands;

use App\Models\CmsMcpCredential;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('cms-mcp:issue
    {name : A label for this credential, such as the bot that will use it}
    {--ability=* : Limit the credential to these abilities. Repeat the option. Defaults to read, draft, and upload}
    {--publish : Also allow the separate publish operation}
    {--expires= : Optional expiration time, parsed as a date}
')]
#[Description('Issue a revocable CMS MCP bearer token. The token is shown once and only its hash is stored.')]
class IssueCmsMcpCredentialCommand extends Command
{
    public function handle(): int
    {
        $name = trim((string) $this->argument('name'));

        if ($name === '') {
            $this->error('A credential name is required.');

            return self::FAILURE;
        }

        $requested = array_values(array_filter(array_map(
            fn (mixed $ability): string => trim((string) $ability),
            (array) $this->option('ability'),
        )));

        $unknown = array_values(array_diff($requested, CmsMcpCredential::ABILITIES));

        if ($unknown !== []) {
            $this->error('Unknown abilities: '.implode(', ', $unknown));
            $this->line('Allowed: '.implode(', ', CmsMcpCredential::ABILITIES));

            return self::FAILURE;
        }

        $abilities = $requested === [] ? CmsMcpCredential::DEFAULT_ABILITIES : $requested;

        if ((bool) $this->option('publish') && ! in_array(CmsMcpCredential::PUBLISH, $abilities, true)) {
            $abilities[] = CmsMcpCredential::PUBLISH;
        }

        $expires = null;

        if (filled($this->option('expires'))) {
            try {
                $expires = Carbon::parse((string) $this->option('expires'));
            } catch (\Throwable) {
                $this->error('The expiration could not be parsed.');

                return self::FAILURE;
            }
        }

        $issued = CmsMcpCredential::issue($name, $abilities, $expires);

        $this->info('Credential '.$issued['credential']->id.' issued for '.$name.'.');
        $this->line('Abilities: '.implode(', ', $abilities));
        $this->warn('Copy this token now. It is stored only as a hash and cannot be shown again.');
        $this->line($issued['token']);

        return self::SUCCESS;
    }
}
