<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('rows', function (Blueprint $table) {
            $table->after('block_id', function (Blueprint $table) {
                $table->unsignedInteger('seat_count')->default(0);
            });
        });
    }
};
