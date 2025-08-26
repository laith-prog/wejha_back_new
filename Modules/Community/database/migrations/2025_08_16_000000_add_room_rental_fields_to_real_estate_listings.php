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
            // Room rental specific fields
            $table->string('gender_preference')->nullable()->after('is_room_rental')->comment('male, female, mixed');
            $table->string('bathroom_type')->nullable()->after('gender_preference')->comment('private, shared');
            $table->boolean('utilities_included')->default(false)->after('bathroom_type')->comment('Water, electricity, gas included');
            $table->boolean('internet_included')->default(false)->after('utilities_included')->comment('Internet/WiFi included');
            $table->string('rent_period')->nullable()->after('internet_included')->comment('daily, weekly, monthly, yearly');
            $table->text('house_rules')->nullable()->after('rent_period')->comment('Rules for room rental');
            $table->integer('max_occupants')->nullable()->after('house_rules')->comment('Maximum number of people allowed');
            $table->boolean('smoking_allowed')->default(false)->after('max_occupants');
            $table->boolean('pets_allowed')->default(false)->after('smoking_allowed');
            $table->time('quiet_hours_start')->nullable()->after('pets_allowed');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('real_estate_listings', function (Blueprint $table) {
            $table->dropColumn([
                'gender_preference',
                'bathroom_type',
                'utilities_included',
                'internet_included',
                'rent_period',
                'house_rules',
                'max_occupants',
                'smoking_allowed',
                'pets_allowed',
                'quiet_hours_start',
                'quiet_hours_end'
            ]);
        });
    }
};
