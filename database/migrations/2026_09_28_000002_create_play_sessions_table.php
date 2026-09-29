<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('play_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('play_date');
            $table->unsignedInteger('fee_amount')->default(15000);
            $table->unsignedInteger('court_count')->default(1);
            $table->enum('status', ['planned', 'active', 'completed'])->default('planned');
            $table->timestamps();

            $table->index(['play_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('play_sessions');
    }
};
