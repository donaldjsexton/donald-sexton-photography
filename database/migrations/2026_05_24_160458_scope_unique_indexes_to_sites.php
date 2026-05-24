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

                $this->dropUniqueOnColumns($table, [$column]);
                $this->addUniqueOnColumns($table, ['site_id', $column]);
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

                $this->dropUniqueOnColumns($table, ['site_id', $column]);
                $this->addUniqueOnColumns($table, [$column]);
            }
        }
    }

    private function scopable(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'site_id');
    }

    /**
     * Drop any unique index covering exactly the given columns, looked up by
     * its real name. Doing this by introspection (rather than assuming the
     * conventional name) keeps the migration safe across databases whose
     * existing schema was created or restored outside these migrations.
     *
     * @param  list<string>  $columns
     */
    private function dropUniqueOnColumns(string $table, array $columns): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false)
                && ! ($index['primary'] ?? false)
                && ($index['columns'] ?? []) === $columns) {
                $this->dropUniqueByName($table, $index['name']);
            }
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function addUniqueOnColumns(string $table, array $columns): void
    {
        if ($this->hasUniqueOnColumns($table, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
            $blueprint->unique($columns);
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasUniqueOnColumns(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }

    private function dropUniqueByName(string $table, string $name): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'pgsql') {
            // A unique may exist as a table constraint or as a bare index, and
            // only one of these statements matches; IF EXISTS keeps the other a
            // no-op so the surrounding transaction is never aborted.
            $grammar = $connection->getSchemaGrammar();

            $connection->statement(sprintf(
                'alter table %s drop constraint if exists %s',
                $grammar->wrapTable($table),
                $grammar->wrap($name),
            ));
            $connection->statement(sprintf('drop index if exists %s', $grammar->wrap($name)));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name): void {
            $blueprint->dropIndex($name);
        });
    }
};
