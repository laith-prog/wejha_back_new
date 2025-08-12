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
        Schema::create('service_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
            $table->string('service_type'); // 'cleaning', 'maintenance', 'professional', 'education'
            $table->json('availability')->nullable(); // JSON array of available days/times
            $table->integer('experience_years')->nullable();
            $table->text('qualifications')->nullable();
            $table->string('service_area')->nullable(); // Geographic area covered
            $table->boolean('is_mobile')->default(false); // Whether provider travels to client
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_listings');
    }
}; 