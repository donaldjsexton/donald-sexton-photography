<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Let editorial content reference a native client gallery, replacing the
     * external Pic-Time embed over time. Nullable so existing content and the
     * Pic-Time path keep working unchanged.
     *
     * @var list<string>
     */
    private array $tables = ['wedding_stories', 'journal_posts'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->foreignId('gallery_id')->nullable()->after('hero_media_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropConstrainedForeignId('gallery_id');
            });
        }
    }
};
