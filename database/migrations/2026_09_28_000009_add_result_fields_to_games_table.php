<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->enum('winner_team', ['A', 'B'])->nullable()->after('status');
            $table->unsignedSmallInteger('team_a_score')->nullable()->after('winner_team');
            $table->unsignedSmallInteger('team_b_score')->nullable()->after('team_a_score');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['winner_team', 'team_a_score', 'team_b_score']);
        });
    }
};
