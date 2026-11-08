<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cms_mcp_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->json('abilities');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_mcp_upload_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_mcp_credential_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 128);
            $table->char('content_sha256', 64);
            $table->foreignId('media_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['cms_mcp_credential_id', 'idempotency_key'], 'cms_mcp_upload_receipts_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_mcp_upload_receipts');
        Schema::dropIfExists('cms_mcp_credentials');
    }
};
