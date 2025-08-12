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
        Schema::table('real_estate_listings', function (Blueprint $table) {
            // Rename 'area' column to 'property_area' to avoid confusion with location area
            $table->renameColumn('area', 'property_area');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('real_estate_listings', function (Blueprint $table) {
            $table->renameColumn('property_area', 'area');
        });
    }
}; 