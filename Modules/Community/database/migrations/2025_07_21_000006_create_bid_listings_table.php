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
        Schema::create('bid_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
            $table->string('bid_type'); // 'investment', 'project', 'auction', 'tender'
            $table->string('bid_code')->nullable(); // Unique code for the bid/tender
            $table->string('main_category')->nullable(); // Main category like 'منشاة قيد التنفيذ' (Facility under construction)
            $table->string('sector')->nullable(); // 'real_estate', 'technology', 'healthcare', 'agriculture', etc.
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('application_link')->nullable(); // Link to apply
            $table->date('submission_start_date')->nullable(); // When bidding starts
            $table->date('submission_end_date')->nullable(); // Deadline for submissions
            $table->boolean('is_facility_under_construction')->default(false);
            $table->decimal('investment_amount_min', 15, 2)->nullable();
            $table->decimal('investment_amount_max', 15, 2)->nullable();
            $table->decimal('expected_return', 8, 2)->nullable(); // percentage
            $table->string('return_period')->nullable(); // 'monthly', 'quarterly', 'yearly'
            $table->integer('investment_term')->nullable(); // in months
            $table->string('risk_level')->nullable(); // 'low', 'medium', 'high'
            $table->boolean('is_equity')->default(false);
            $table->boolean('is_debt')->default(false);
            $table->decimal('equity_percentage', 8, 2)->nullable();
            $table->json('business_plan')->nullable(); // JSON with business plan details
            $table->json('financial_projections')->nullable(); // JSON with financial projections
            $table->json('documents')->nullable(); // JSON array of document URLs
            $table->json('requirements')->nullable(); // JSON array of requirements for bidders
            $table->json('terms_and_conditions')->nullable(); // JSON array of terms and conditions
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bid_listings');
    }
}; 