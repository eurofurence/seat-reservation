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
        Schema::table('seats', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->dropTimestamps();
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropTimestamps();
        });

        Schema::table('rows', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seats', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->timestamps();
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->timestamps();
        });

        Schema::table('rows', function (Blueprint $table) {
            $table->timestamps();
        });
    }
};