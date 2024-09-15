<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->integer('default_seat_count')->after('name')->default(0);
        });
    }
};
