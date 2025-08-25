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
            $table->enum('alignment', ['left', 'center', 'right'])->default('center')->after('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rows', function (Blueprint $table) {
            $table->dropColumn('alignment');
        });
    }
};
