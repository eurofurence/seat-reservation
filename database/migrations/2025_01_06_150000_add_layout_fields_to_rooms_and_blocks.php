<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->json('layout_config')->nullable()->after('name');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->integer('position_x')->default(0)->after('room_id');
            $table->integer('position_y')->default(0)->after('position_x');
            $table->integer('rotation')->default(0)->after('position_y'); // 0, 90, 180, 270
            $table->integer('z_index')->default(1)->after('rotation');
            
            // Add grid-based positioning fields
            $table->integer('grid_column')->default(1)->after('z_index');
            $table->integer('grid_row')->default(1)->after('grid_column');
            $table->integer('grid_column_span')->default(1)->after('grid_row');
            $table->integer('grid_row_span')->default(1)->after('grid_column_span');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('layout_config');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y', 'rotation', 'z_index', 'grid_column', 'grid_row', 'grid_column_span', 'grid_row_span']);
        });
    }
};
