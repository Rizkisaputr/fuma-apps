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

        if (Schema::hasColumn('play_sessions', 'description')) {
            Schema::table('play_sessions', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('play_sessions', 'description')) {
            Schema::table('play_sessions', function (Blueprint $table) {
                $table->text('description')->nullable()->after('session_type');
            });
        }
    }
};
