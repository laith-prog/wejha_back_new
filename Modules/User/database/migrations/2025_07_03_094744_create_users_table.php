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
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('fname'); 
            $table->string('lname');
            $table->string('type')->nullable(); 
            $table->string('email')->unique(); 
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); 
            $table->string('phone')->nullable(); 
            $table->enum('gender', ['male', 'female', 'other'])->nullable(); 
            $table->date('birthday')->nullable(); 
            $table->string('auth_provider')->default('email'); 
            $table->string('photo')->nullable(); 
            $table->unsignedBigInteger('role_id')->nullable(); // Make role_id nullable
            $table->text('refresh_token_hash')->nullable();
            $table->timestamp('refresh_token_expires_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
