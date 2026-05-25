<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->morphs('actor');
            $table->string('type')->index();
            $table->nullableMorphs('subject');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id', 'created_at'], 'portal_activities_actor_timeline_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_activities');
    }
};
