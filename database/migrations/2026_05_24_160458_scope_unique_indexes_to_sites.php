<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert globally-unique columns on tenant-scoped tables into composite
     * (site_id, column) uniques, so two tenants can each have an "about" page,
     * an INV-0001, the same admin email, etc.
     *
     * @var array<string, list<string>>
     */
    private array $map = [
        'pages' => ['slug'],
        'wedding_stories' => ['slug'],
        'journal_posts' => ['slug'],
        'collections' => ['slug'],
        'categories' => ['slug'],
        'tags' => ['slug'],
        'venues' => ['slug', 'google_places_id'],
        'redirects' => ['from_path'],
        'users' => ['email'],
        'invoices' => ['number'],
        'contracts' => ['number'],
    ];

    public function up(): void
    {
        foreach ($this->map as $table => $columns) {
            if (! $this->scopable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropUnique([$column]);
                    $blueprint->unique(['site_id', $column]);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->map as $table => $columns) {
            if (! $this->scopable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropUnique(['site_id', $column]);
                    $blueprint->unique([$column]);
                });
            }
        }
    }

    private function scopable(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'site_id');
    }
};
