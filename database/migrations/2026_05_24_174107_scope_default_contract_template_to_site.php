<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The "one default template" rule was enforced by a global partial unique
     * index, so only a single tenant in the whole platform could have a
     * default. Re-scope it to (site_id) so every site keeps exactly one
     * default of its own.
     */
    public function up(): void
    {
        $this->collapseDuplicateDefaultsPerSite();

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS contract_templates_one_default_unique');
            DB::statement('CREATE UNIQUE INDEX contract_templates_one_default_unique ON contract_templates (site_id) WHERE is_default = 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS contract_templates_one_default_unique');
            DB::statement('CREATE UNIQUE INDEX contract_templates_one_default_unique ON contract_templates (site_id) WHERE is_default IS TRUE');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS contract_templates_one_default_unique');
            DB::statement('CREATE UNIQUE INDEX contract_templates_one_default_unique ON contract_templates (is_default) WHERE is_default = 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS contract_templates_one_default_unique');
            DB::statement('CREATE UNIQUE INDEX contract_templates_one_default_unique ON contract_templates (is_default) WHERE is_default IS TRUE');
        }
    }

    /**
     * Keep the lowest-id default per site and demote the rest, so the new
     * per-site unique index can be created without collisions.
     */
    private function collapseDuplicateDefaultsPerSite(): void
    {
        $defaultsBySite = DB::table('contract_templates')
            ->where('is_default', true)
            ->orderBy('id')
            ->get(['id', 'site_id'])
            ->groupBy('site_id');

        foreach ($defaultsBySite as $rows) {
            $extra = $rows->slice(1)->pluck('id');

            if ($extra->isNotEmpty()) {
                DB::table('contract_templates')
                    ->whereIn('id', $extra->all())
                    ->update(['is_default' => false]);
            }
        }
    }
};
