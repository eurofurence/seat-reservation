<?php

use App\Models\Event;
use App\Models\Seat;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Event::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Seat::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'seat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
