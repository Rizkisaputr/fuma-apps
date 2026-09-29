<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_session_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('game_number');
            $table->enum('status', ['waiting', 'playing', 'completed'])->default('waiting');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['play_session_id', 'game_number']);
            $table->index(['play_session_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
