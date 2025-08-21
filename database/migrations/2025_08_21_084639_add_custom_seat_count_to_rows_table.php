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
        Schema::table('rows', function (Blueprint $table) {
            $table->integer('custom_seat_count')->nullable()->after('seats_count')
                ->comment('Custom seat count set in room planner, null means use default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rows', function (Blueprint $table) {
            $table->dropColumn('custom_seat_count');
        });
    }
};