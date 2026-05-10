<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->nullableMorphs('billable');
            $table->string('net_terms')->nullable();
        });

        DB::table('invoices')
            ->whereNotNull('client_id')
            ->update([
                'billable_type' => Client::class,
                'billable_id' => DB::raw('client_id'),
            ]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropIndex(['client_id', 'status']);
            $table->dropColumn('client_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['billable_type', 'billable_id', 'status'], 'invoices_billable_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_billable_status_index');
            $table->foreignId('client_id')->nullable()->after('number')->constrained('clients')->cascadeOnDelete();
            $table->index(['client_id', 'status']);
        });

        DB::statement('UPDATE invoices SET client_id = billable_id WHERE billable_type = ?', [Client::class]);

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropMorphs('billable');
            $table->dropColumn('net_terms');
        });
    }
};
