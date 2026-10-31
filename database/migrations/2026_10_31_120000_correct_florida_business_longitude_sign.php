<?php

use App\Support\FloridaGeo;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Forward-only data correction. Reversing it would put Clearwater back in Asia.
     */
    public function up(): void
    {
        FloridaGeo::correctStoredSettings();
    }

    public function down(): void
    {
        //
    }
};
