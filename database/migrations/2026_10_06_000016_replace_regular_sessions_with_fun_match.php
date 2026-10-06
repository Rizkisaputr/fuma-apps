<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('play_sessions')
            ->where('session_type', 'regular')
            ->update(['session_type' => 'fun_match', 'updated_at' => now()]);

        Schema::table('play_sessions', function (Blueprint $table) {
            $table->string('session_type', 30)->default('fun_match')->change();
        });
    }

    public function down(): void
    {
        Schema::table('play_sessions', function (Blueprint $table) {
            $table->string('session_type', 30)->default('regular')->change();
        });
    }
};
