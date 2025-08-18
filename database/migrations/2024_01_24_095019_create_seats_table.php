<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('row_id')->constrained()->onDelete('cascade');
            $table->integer('number');
            $table->string('label');
            $table->timestamps();
            
            $table->index(['row_id', 'number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('seats');
    }
};