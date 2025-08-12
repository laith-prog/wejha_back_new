<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('category_id')->after('phone_number')->nullable();
            $table->foreignId('subcategory_id')->after('category_id')->nullable();
            $table->foreign('category_id')->references('id')->on('listing_categories');
            $table->foreign('subcategory_id')->references('id')->on('listing_subcategories');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['subcategory_id']);
            $table->dropColumn(['category_id', 'subcategory_id']);
        });
    }
}; 