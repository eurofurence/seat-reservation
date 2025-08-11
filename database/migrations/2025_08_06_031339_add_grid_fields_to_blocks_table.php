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
        Schema::table('blocks', function (Blueprint $table) {
            $table->integer('grid_column')->default(1)->after('z_index');
            $table->integer('grid_row')->default(1)->after('grid_column');
            $table->integer('grid_column_span')->default(1)->after('grid_row');
            $table->integer('grid_row_span')->default(1)->after('grid_column_span');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn(['grid_column', 'grid_row', 'grid_column_span', 'grid_row_span']);
        });
    }
};
