<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wedding_questionnaires', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('inquiry_id')->constrained()->nullOnDelete();
        });

        Schema::table('wedding_questionnaires', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->nullable()->change();
        });

        DB::statement('update wedding_questionnaires set client_id = (select inquiries.client_id from inquiries where inquiries.id = wedding_questionnaires.inquiry_id) where client_id is null and inquiry_id is not null');
    }

    public function down(): void
    {
        DB::table('wedding_questionnaires')->whereNull('inquiry_id')->delete();

        Schema::table('wedding_questionnaires', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });

        Schema::table('wedding_questionnaires', function (Blueprint $table) {
            $table->foreignId('inquiry_id')->nullable(false)->change();
        });
    }
};
