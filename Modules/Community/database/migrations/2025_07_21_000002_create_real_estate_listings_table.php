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
        Schema::create('real_estate_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
            $table->string('property_type'); // 'apartment', 'house', 'villa', 'land', 'commercial', 'room'
            $table->string('offer_type'); // 'rent', 'sell'
            $table->integer('room_number')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('area', 10, 2)->nullable(); // in square meters
            $table->integer('floors')->nullable();
            $table->integer('floor_number')->nullable(); // for apartments
            $table->boolean('has_parking')->default(false);
            $table->boolean('has_garden')->default(false);
            $table->integer('balcony')->default(0);
            $table->boolean('has_pool')->default(false);
            $table->boolean('has_elevator')->default(false);
            $table->string('furnished')->default('no');
            $table->year('year_built')->nullable();
            $table->string('ownership_type')->nullable(); // 'freehold', 'leasehold', etc.
            $table->string('legal_status')->nullable(); // 'ready', 'under_construction', 'off_plan'
            $table->json('amenities')->nullable(); // JSON array of amenities
            $table->boolean('is_room_rental')->default(false)->comment('True if only renting a room in a property');
            $table->decimal('room_area', 10, 2)->nullable()->comment('Area of the room if room rental');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('real_estate_listings');
    }
}; 