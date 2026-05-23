<?php

use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2b: scope the authenticatables and billing entities. Payments are
     * intentionally left global — webhooks resolve them by gateway id with no
     * tenant context and reach the (scoped) invoice via withoutSiteScope().
     *
     * @var list<string>
     */
    private array $tables = [
        'users',
        'clients',
        'venues',
        'invoices',
        'contracts',
        'contract_templates',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'site_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('site_id')->nullable()->after('id')->index();
                });
            }
        }

        $defaultSiteId = (int) (Site::default()?->id ?? DB::table('sites')->orderBy('id')->value('id'));

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->whereNull('site_id')->update(['site_id' => $defaultSiteId]);
            }
        }

        foreach ($this->tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('site_id')->nullable(false)->change();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'site_id')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('site_id');
                });
            }
        }
    }
};
