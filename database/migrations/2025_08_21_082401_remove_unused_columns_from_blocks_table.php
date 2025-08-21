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
            // Remove unused columns
            $table->dropColumn([
                'rotation_degrees',
                'rotate',
                'z_index',
                'grid_column',
                'grid_row', 
                'grid_column_span',
                'grid_row_span',
                'grid_x',
                'grid_y',
                'grid_width',
                'grid_height',
                'row',
                'row_count',
                'default_seat_count'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            // Restore the removed columns with their original defaults
            $table->decimal('rotation_degrees', 5, 2)->default(0.00);
            $table->integer('rotate')->default(0);
            $table->integer('z_index')->default(1);
            $table->integer('grid_column')->default(1);
            $table->integer('grid_row')->default(1);
            $table->integer('grid_column_span')->default(1);
            $table->integer('grid_row_span')->default(1);
            $table->integer('grid_x')->default(0);
            $table->integer('grid_y')->default(0);
            $table->integer('grid_width')->default(1);
            $table->integer('grid_height')->default(1);
            $table->unsignedInteger('row')->default(1);
            $table->unsignedInteger('row_count')->default(0);
            $table->integer('default_seat_count')->default(0);
        });
    }
};