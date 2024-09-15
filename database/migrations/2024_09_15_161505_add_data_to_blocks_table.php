<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->after('room_id', function (Blueprint $table) {
                $table->unsignedInteger('row')->default(1);
                $table->integer('rotate')->default(0);
                $table->unsignedInteger('row_count')->default(0);
            });
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            //
        });
    }
};
