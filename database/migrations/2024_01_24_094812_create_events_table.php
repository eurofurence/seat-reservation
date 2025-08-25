<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Room::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            // Reservation Deadline
            $table->dateTime('reservation_ends_at')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
