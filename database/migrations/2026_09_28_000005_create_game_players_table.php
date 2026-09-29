<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->enum('team', ['A', 'B']);
            $table->enum('slot', [1, 2]);
            $table->timestamps();

            $table->unique(['game_id', 'member_id']);
            $table->unique(['game_id', 'team', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_players');
    }
};
