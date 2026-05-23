<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables that gain a site_id in Phase 1 (the public content + block
     * engine). 'blocks' already carries the column from the engine work.
     *
     * @var list<string>
     */
    private array $tables = [
        'pages',
        'wedding_stories',
        'journal_posts',
        'media',
        'homepage_settings',
        'site_settings',
        'blocks',
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

        $defaultSiteId = $this->ensureDefaultSite();

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
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'site_id')) {
                continue;
            }

            if ($table === 'blocks') {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->unsignedBigInteger('site_id')->nullable()->change();
                });

                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('site_id');
            });
        }
    }

    private function ensureDefaultSite(): int
    {
        $existing = DB::table('sites')->where('is_default', true)->value('id')
            ?? DB::table('sites')->orderBy('id')->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return (int) DB::table('sites')->insertGetId([
            'name' => (string) config('app.name', 'Default Site'),
            'subdomain' => 'www',
            'primary_domain' => $host,
            'is_default' => true,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
