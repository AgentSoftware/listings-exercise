<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Optional — buyers rarely name a search, so the UI falls back to
            // describing the criteria. See SavedSearch::label().
            $table->string('name')->nullable();

            // The criteria, one column each rather than a JSON blob. The nightly
            // alert run matches listings against these in SQL, which needs real
            // columns; it also keeps the values typed for static analysis.
            // Null means "any", matching ListingCriteria.
            $table->string('property_type')->nullable();
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();
            $table->string('region')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
