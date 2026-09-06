<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            // When the digest covering this alert was sent. Distinct from
            // read_at, which is the buyer marking it read in the UI.
            //
            // This is what a run hands to the delivery step, replacing "keep
            // every created alert in memory and group it there". It also makes
            // delivery restartable: a run that dies half way through leaves the
            // rest un-notified, and the next run picks them up.
            $table->timestamp('notified_at')->nullable();

            // Most rows end up notified, so the index earns its keep finding
            // the few that aren't.
            $table->index('notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex(['notified_at']);
            $table->dropColumn('notified_at');
        });
    }
};
