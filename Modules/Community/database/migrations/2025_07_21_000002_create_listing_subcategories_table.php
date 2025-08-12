<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('listing_categories')->onDelete('cascade');
            $table->string('name'); // e.g., 'apartment', 'house', 'villa' for real_estate
            $table->string('display_name');
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->json('attributes')->nullable(); // Store specific attributes for this subcategory
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_subcategories');
    }
}; 