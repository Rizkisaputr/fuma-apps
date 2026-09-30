<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->string('game_format', 30)->default('best_of_three')->after('status');
            $table->unsignedSmallInteger('point_target')->default(21)->after('game_format');
        });

        Schema::create('game_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('set_number');
            $table->unsignedSmallInteger('team_a_score');
            $table->unsignedSmallInteger('team_b_score');
            $table->enum('winner_team', ['A', 'B']);
            $table->timestamps();

            $table->unique(['game_id', 'set_number']);
        });

        DB::table('games')->update(['game_format' => 'single_set']);

        DB::table('games')
            ->whereNotNull('team_a_score')
            ->whereNotNull('team_b_score')
            ->orderBy('id')
            ->eachById(function (object $game): void {
                DB::table('game_sets')->insert([
                    'game_id' => $game->id,
                    'set_number' => 1,
                    'team_a_score' => $game->team_a_score,
                    'team_b_score' => $game->team_b_score,
                    'winner_team' => $game->winner_team
                        ?? ($game->team_a_score > $game->team_b_score ? 'A' : 'B'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_sets');

        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['game_format', 'point_target']);
        });
    }
};
