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
        Schema::create('listing_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->comment('User who reported');
            $table->unsignedBigInteger('listing_id');
            $table->foreign('listing_id')->references('id')->on('listings')->onDelete('cascade');
            $table->string('reason'); // 'inappropriate', 'spam', 'fraud', 'duplicate', 'misleading', 'offensive', 'other'
            $table->text('details')->nullable();
            $table->json('evidence')->nullable()->comment('URLs or IDs of evidence files');
            $table->string('status')->default('pending'); // 'pending', 'under_review', 'resolved', 'rejected'
            $table->text('admin_notes')->nullable();
            $table->string('action_taken')->nullable(); // 'none', 'warning', 'temporary_suspension', 'permanent_ban', 'listing_removed'
            $table->uuid('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('notify_reporter')->default(true);
            $table->boolean('reporter_notified')->default(false);
            $table->timestamp('reporter_notified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_reports');
    }
}; 