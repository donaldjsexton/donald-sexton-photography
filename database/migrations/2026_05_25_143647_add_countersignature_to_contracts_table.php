<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('countersigner_name')->nullable()->after('signer_user_agent');
            $table->string('countersigner_email')->nullable()->after('countersigner_name');
            $table->string('countersigner_ip', 45)->nullable()->after('countersigner_email');
            $table->string('countersigner_user_agent', 512)->nullable()->after('countersigner_ip');
            $table->foreignId('countersigned_by')->nullable()->after('countersigner_user_agent')->constrained('users')->nullOnDelete();
            $table->timestamp('countersigned_at')->nullable()->after('signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('countersigned_by');
            $table->dropColumn([
                'countersigner_name',
                'countersigner_email',
                'countersigner_ip',
                'countersigner_user_agent',
                'countersigned_at',
            ]);
        });
    }
};
