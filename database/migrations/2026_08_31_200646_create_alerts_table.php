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
            $table->foreignId('saved_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // This is what makes a listing "new": if a row already exists
            // for this pair, it's already been alerted on and gets skipped
            // on future runs — no separate "last checked" state needed.
            $table->unique(['saved_search_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
