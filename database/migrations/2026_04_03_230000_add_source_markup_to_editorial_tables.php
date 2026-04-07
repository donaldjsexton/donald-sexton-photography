<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wedding_stories', function (Blueprint $table) {
            $table->longText('source_markup')->nullable()->after('body');
        });

        Schema::table('journal_posts', function (Blueprint $table) {
            $table->longText('source_markup')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('wedding_stories', function (Blueprint $table) {
            $table->dropColumn('source_markup');
        });

        Schema::table('journal_posts', function (Blueprint $table) {
            $table->dropColumn('source_markup');
        });
    }
};
