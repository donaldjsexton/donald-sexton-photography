<?php

namespace App\Console\Commands;

use App\Support\HomepageBlocksSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('home:seed-blocks')]
#[Description('Build the default ordered homepage section blocks so the homepage renders through the block engine.')]
class SeedHomepageBlocks extends Command
{
    public function handle(): int
    {
        $created = HomepageBlocksSeeder::seed();

        $this->info($created > 0
            ? sprintf('Created %d homepage block(s).', $created)
            : 'Homepage already has blocks; nothing to do.');

        return self::SUCCESS;
    }
}
