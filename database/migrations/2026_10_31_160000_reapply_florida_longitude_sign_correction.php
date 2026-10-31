<?php

use App\Support\FloridaGeo;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * The first correction only matched a region of FL or a locality of
     * Clearwater. Production's schema still showed those labels via the
     * address fallbacks, so that migration updated nothing. Run the corrected
     * rule once on the next deploy.
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
