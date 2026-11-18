<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An alert is the persisted match record: one row per user per listing.
        // Whether, and when, it's emailed is a separate concern (notified_at).
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            // Deleting a saved search keeps the alerts it already raised.
            $table->foreignId('saved_search_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // One alert per listing per user: dedupes overlapping searches, and
            // stops a listing that goes live again from alerting twice.
            $table->unique(['user_id', 'listing_id']);

            // Pruning filters on age, and on read state.
            $table->index('created_at');
            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
