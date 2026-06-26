<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Top-level client gallery (e.g. "Smith Wedding"). Site-scoped, addressable
     * publicly by uuid or per-site slug, optionally password-protected. Holds
     * albums; the CRM client link is added in a later phase.
     */
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('slug')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->string('password')->nullable();
            $table->foreignId('cover_photo_id')->nullable()->constrained('photos')->nullOnDelete();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index(['site_id', 'visibility']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
