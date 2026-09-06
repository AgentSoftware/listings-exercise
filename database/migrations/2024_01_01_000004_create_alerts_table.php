<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // Which search matched first, for display only. Kept when that search
            // is deleted: an alert records something we already told the user
            // about, and cascading would also delete alerts that a second,
            // still-live search matched.
            $table->foreignId('saved_search_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // One listing, one alert per user, however many of their searches it
            // matched. This is the anti-spam guarantee, so it lives in the schema
            // rather than only in the code that writes alerts.
            $table->unique(['user_id', 'listing_id']);

            // The alerts page lists one user's alerts, newest first — ordered by
            // id, not created_at: a run inserts every alert it creates in one
            // statement, so they all share a created_at and ordering by it would
            // put the same row on two pages.
            $table->index(['user_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
