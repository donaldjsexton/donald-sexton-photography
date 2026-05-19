<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        DB::table('payments')
            ->where('gateway', '!=', 'manual')
            ->whereNotNull('gateway_payment_id')
            ->select('id', 'gateway', 'gateway_payment_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($row) => $row->gateway.'|'.$row->gateway_payment_id)
            ->filter(fn ($rows) => $rows->count() > 1)
            ->each(function ($rows) {
                $keep = $rows->shift();
                DB::table('payments')
                    ->whereIn('id', $rows->pluck('id'))
                    ->update(['gateway_payment_id' => null]);
            });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway', 'gateway_payment_id']);
        });

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX payments_gateway_payment_id_unique ON payments (gateway, gateway_payment_id) WHERE gateway_payment_id IS NOT NULL');
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->unique(['gateway', 'gateway_payment_id'], 'payments_gateway_payment_id_unique');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS payments_gateway_payment_id_unique');
        } else {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropUnique('payments_gateway_payment_id_unique');
            });
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['gateway', 'gateway_payment_id']);
        });
    }
};
