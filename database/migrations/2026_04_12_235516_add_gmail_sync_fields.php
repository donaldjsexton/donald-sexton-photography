<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->string('gmail_thread_id')->nullable()->after('calendar_event_id');
            $table->index('gmail_thread_id');
        });

        Schema::table('inquiry_messages', function (Blueprint $table): void {
            $table->string('gmail_message_id')->nullable()->unique()->after('sent_at');
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->string('gmail_last_history_id')->nullable();
            $table->timestamp('gmail_last_synced_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inquiries', function (Blueprint $table): void {
            $table->dropIndex(['gmail_thread_id']);
            $table->dropColumn('gmail_thread_id');
        });

        Schema::table('inquiry_messages', function (Blueprint $table): void {
            $table->dropUnique(['gmail_message_id']);
            $table->dropColumn('gmail_message_id');
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['gmail_last_history_id', 'gmail_last_synced_at']);
        });
    }
};
