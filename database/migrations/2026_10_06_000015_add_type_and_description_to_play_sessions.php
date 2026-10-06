<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->string('session_type', 30)->default('fun_match')->after('play_date');
            $table->index(['session_type', 'play_date']);
        });
    }

    public function down(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->dropIndex(['session_type', 'play_date']);
            $table->dropColumn('session_type');
        });
    }
};
