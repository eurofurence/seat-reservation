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
        Schema::table('bookings', function (Blueprint $table) {
            // Name of the admin who created a manual/CSV-imported booking on a guest's
            // behalf, captured as a plain string at creation time (no FK/join needed to
            // display it, and it stays accurate even if the admin's account is later
            // renamed or deleted). Separate from user_id, which is the customer's own
            // account on self-service bookings - never repurpose user_id for this.
            $table->string('created_by_name')->nullable()->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('created_by_name');
        });
    }
};
