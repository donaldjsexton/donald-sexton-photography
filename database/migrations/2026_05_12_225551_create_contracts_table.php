<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number')->unique();
            $table->nullableMorphs('billable');
            $table->foreignId('booked_job_id')->nullable()->constrained('booked_jobs')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('contract_template_id')->nullable()->constrained('contract_templates')->nullOnDelete();

            $table->string('status')->default('draft')->index();
            $table->string('title');
            $table->longText('body');
            $table->date('issue_date');
            $table->date('expires_at')->nullable();

            $table->string('signer_name')->nullable();
            $table->string('signer_email')->nullable();
            $table->string('signer_ip', 45)->nullable();
            $table->string('signer_user_agent', 512)->nullable();

            $table->text('internal_notes')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['billable_type', 'billable_id', 'status'], 'contracts_billable_status_index');
            $table->index('issue_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
