<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_session_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('play_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->timestamp('attended_at');
            $table->unsignedInteger('fee_amount');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['play_session_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_session_members');
    }
};
