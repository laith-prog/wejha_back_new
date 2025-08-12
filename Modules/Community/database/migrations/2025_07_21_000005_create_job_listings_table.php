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
        Schema::create('job_listings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('listing_id');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
            $table->string('job_title');
            $table->string('company_name');
            $table->string('job_type'); // 'full_time', 'part_time', 'contract', 'temporary', 'internship'
            $table->string('attendance_type')->nullable(); // 'office', 'remote', 'hybrid'
            $table->string('job_category')->nullable(); // 'programming', 'design', 'marketing', etc.
            $table->string('job_subcategory')->nullable(); // 'frontend', 'backend', 'fullstack', etc.
            $table->string('gender_preference')->nullable(); // 'male', 'female', 'any'
            $table->decimal('salary', 12, 2)->nullable();
            $table->string('salary_period')->nullable(); // 'hourly', 'daily', 'weekly', 'monthly', 'yearly'
            $table->string('salary_currency', 10)->default('USD');
            $table->boolean('is_salary_negotiable')->default(false);
            $table->integer('experience_years_min')->nullable();
            $table->string('education_level')->nullable(); // 'high_school', 'bachelor', 'master', 'phd'
            $table->string('required_language')->nullable();
            $table->string('company_size')->nullable(); // '1-10', '11-50', '51-200', '201-500', '501+'
            $table->json('benefits')->nullable(); // JSON array of benefits like 'transportation_allowance', 'health_insurance', etc.
            $table->string('application_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_listings');
    }
}; 