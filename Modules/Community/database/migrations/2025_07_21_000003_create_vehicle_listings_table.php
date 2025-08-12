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
        Schema::create('vehicle_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
            $table->string('vehicle_type'); // 'car', 'motorcycle', 'boat', 'truck'
            $table->string('make'); // Toyota, Honda, etc.
            $table->string('model');
            $table->year('year');
            $table->decimal('mileage', 10, 2)->nullable();
            $table->string('color')->nullable();
            $table->string('transmission')->nullable(); // 'automatic', 'manual', etc.
            $table->string('fuel_type')->nullable(); // 'gasoline', 'diesel', 'electric', etc.
            $table->string('engine_size')->nullable();
            $table->string('condition')->nullable(); // 'new', 'used', 'excellent', etc.
            $table->string('body_type')->nullable(); // 'sedan', 'suv', etc.
            $table->integer('doors')->nullable();
            $table->integer('seats')->nullable();
            $table->json('features')->nullable(); // JSON array of features
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_listings');
    }
}; 