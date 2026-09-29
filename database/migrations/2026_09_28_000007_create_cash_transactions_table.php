<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('play_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('play_session_member_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('transaction_date');
            $table->unsignedBigInteger('amount');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique('play_session_member_id');
            $table->index('transaction_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
