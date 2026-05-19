<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        $defaults = DB::table('contract_templates')
            ->where('is_default', true)
            ->orderBy('id')
            ->pluck('id');

        if ($defaults->count() > 1) {
            DB::table('contract_templates')
                ->whereIn('id', $defaults->slice(1)->values())
                ->update(['is_default' => false]);
        }

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX contract_templates_one_default_unique ON contract_templates (is_default) WHERE is_default = 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX contract_templates_one_default_unique ON contract_templates (is_default) WHERE is_default IS TRUE');
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS contract_templates_one_default_unique');
        }
    }
};
