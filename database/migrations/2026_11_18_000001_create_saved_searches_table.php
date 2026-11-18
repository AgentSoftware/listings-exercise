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
            $table->string('name')->nullable();

            // Criteria are columns (not JSON or a filters table) so the reverse
            // match, "which saved searches does this new listing satisfy?", is a
            // single indexed query. Null means "any". Types match `listings`.
            $table->string('region')->nullable();
            $table->string('property_type')->nullable();
            $table->unsignedInteger('max_price')->nullable();
            $table->unsignedTinyInteger('min_bedrooms')->nullable();

            // Defaults to never, so a row created without an explicit choice
            // can never email anyone.
            $table->string('alert_frequency')->default('never');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            // The reverse match always filters on alert_frequency, and region is
            // the most selective criterion.
            $table->index(['alert_frequency', 'region']);
            $table->index('property_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
