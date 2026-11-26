<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('partner_name')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('type', 32)->default('prospect');
            $table->string('stage', 32)->default('lead');
            $table->string('shoot_type', 64)->nullable();
            $table->date('event_date')->nullable();
            $table->string('source', 64);
            $table->string('source_detail')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('first_contact_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->unsignedBigInteger('client_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['site_id', 'email']);
            $table->index(['type', 'stage']);
        });

        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->string('email');
            $table->unsignedBigInteger('contact_id')->nullable()->index();
            $table->string('reason')->nullable();
            $table->string('source', 32);
            $table->timestamp('suppressed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['site_id', 'email']);
        });

        foreach (['inquiries', 'quiz_leads', 'clients'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('contact_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['inquiries', 'quiz_leads', 'clients'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('contact_id');
            });
        }

        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('contacts');
    }
};
