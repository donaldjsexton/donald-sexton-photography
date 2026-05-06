<?php

use App\Models\Inquiry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inquiry_messages', function (Blueprint $table): void {
            $table->string('gmail_thread_id')->nullable()->after('gmail_message_id');
            $table->string('subject')->nullable()->after('gmail_thread_id');
            $table->index(['inquiry_id', 'gmail_thread_id'], 'inquiry_messages_inquiry_thread_idx');
        });

        Inquiry::query()
            ->whereNotNull('gmail_thread_id')
            ->orderBy('id')
            ->chunkById(200, function ($inquiries): void {
                foreach ($inquiries as $inquiry) {
                    $inquiry->messages()
                        ->whereNotNull('gmail_message_id')
                        ->whereNull('gmail_thread_id')
                        ->update(['gmail_thread_id' => $inquiry->gmail_thread_id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('inquiry_messages', function (Blueprint $table): void {
            $table->dropIndex('inquiry_messages_inquiry_thread_idx');
            $table->dropColumn(['gmail_thread_id', 'subject']);
        });
    }
};
