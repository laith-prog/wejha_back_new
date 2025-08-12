<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('price_type')->nullable(); // 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'total', etc.
            $table->string('currency', 10)->default('USD');
            $table->string('post_number')->unique();
            $table->string('phone_number')->nullable();
            $table->string('listing_type')->nullable(); // 'real_estate', 'vehicle', 'service', 'job', 'bid' - for backward compatibility
            $table->string('purpose')->nullable(); // 'sell', 'rent', 'offer', 'seek', etc.
            $table->string('status')->default('active'); // 'active', 'inactive', 'pending', 'sold', 'rented', etc.
            $table->boolean('facility_under_construction')->default(false);
            $table->date('expected_completion_date')->nullable();
            $table->integer('construction_progress_percent')->nullable();
            
            // Location details
            $table->decimal('lat', 10, 8)->nullable(); // Latitude
            $table->decimal('lng', 11, 8)->nullable(); // Longitude
            $table->string('city')->nullable();        // City
            $table->string('area')->nullable();        // Area/Neighborhood
            
            $table->json('features')->nullable(); // JSON array of features
            $table->json('similar_options')->nullable(); // JSON array of similar listing options
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_promoted')->default(false);
            $table->timestamp('promoted_until')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('views_count')->default(0);
            $table->integer('favorites_count')->default(0);
            $table->integer('reports_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
}; 