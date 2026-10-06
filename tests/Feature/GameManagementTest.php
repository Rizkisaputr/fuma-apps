<?php

namespace Tests\Feature;

use App\Livewire\Sessions\Show;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Member;
use App\Models\PlaySession;
use App\Models\PlaySessionMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class GameManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_game_requires_exactly_four_attending_players(): void
    {
        [$session, $members] = $this->sessionWithAttendees(3);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->set('gameFormat', Game::FORMAT_BEST_OF_THREE)
            ->set('teamA', [$members[0]->id, $members[1]->id])
            ->set('teamB', [$members[2]->id])
            ->call('saveGame')
            ->assertHasErrors('teamB');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_non_attending_player_cannot_be_added_to_game(): void
    {
        [$session, $members] = $this->sessionWithAttendees(3);
        $outsider = Member::query()->create(['name' => 'Tidak Hadir', 'skill_level' => 'pro']);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->set('gameFormat', Game::FORMAT_BEST_OF_THREE)
            ->set('teamA', [$members[0]->id, $members[1]->id])
            ->set('teamB', [$members[2]->id, $outsider->id])
            ->call('saveGame')
            ->assertHasErrors('game');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_same_player_cannot_fill_two_slots(): void
    {
        [$session, $members] = $this->sessionWithAttendees(4);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->set('gameFormat', Game::FORMAT_BEST_OF_THREE)
            ->set('teamA', [$members[0]->id, $members[1]->id])
            ->set('teamB', [$members[2]->id, $members[0]->id])
            ->call('saveGame')
            ->assertHasErrors('game');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_games_receive_sequential_numbers_and_manual_teams(): void
    {
        [$session, $members] = $this->sessionWithAttendees(8);
        $component = $this->sessionComponent($session);

        $this->saveGame($component, array_slice($members, 0, 4));
        $this->saveGame($component, array_slice($members, 4, 4));

        $this->assertDatabaseHas('games', ['play_session_id' => $session->id, 'game_number' => 1, 'status' => 'waiting']);
        $this->assertDatabaseHas('games', ['play_session_id' => $session->id, 'game_number' => 2, 'status' => 'waiting']);

        $firstGame = Game::query()->where('game_number', 1)->firstOrFail();
        $this->assertDatabaseHas('game_players', ['game_id' => $firstGame->id, 'member_id' => $members[0]->id, 'team' => 'A', 'slot' => 1]);
        $this->assertDatabaseHas('game_players', ['game_id' => $firstGame->id, 'member_id' => $members[3]->id, 'team' => 'B', 'slot' => 2]);
    }

    public function test_player_who_is_playing_cannot_be_placed_in_another_game(): void
    {
        [$session, $members] = $this->sessionWithAttendees(7);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4));

        $firstGame = Game::query()->firstOrFail();
        $component->call('startGame', $firstGame->id)->assertHasNoErrors('game');

        $this->saveGame($component, [$members[0], $members[4], $members[5], $members[6]], false)
            ->assertHasErrors('game');

        $this->assertDatabaseCount('games', 1);
    }

    public function test_only_one_game_can_be_playing_in_a_session(): void
    {
        [$session, $members] = $this->sessionWithAttendees(8);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4));
        $this->saveGame($component, array_slice($members, 4, 4));

        $games = Game::query()->orderBy('game_number')->get();
        $component->call('startGame', $games[0]->id)->assertHasNoErrors('game');
        $component->call('startGame', $games[1]->id)->assertHasErrors('game');

        $this->assertDatabaseHas('games', ['id' => $games[0]->id, 'status' => 'playing']);
        $this->assertDatabaseHas('games', ['id' => $games[1]->id, 'status' => 'waiting']);
        $this->assertSame(1, Game::query()->where('status', 'playing')->count());
    }

    public function test_completing_game_records_winner_and_only_then_increases_play_count(): void
    {
        [$session, $members] = $this->sessionWithAttendees(5);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4));
        $game = Game::query()->firstOrFail();

        $component->call('startGame', $game->id)->assertHasNoErrors('game');
        $this->assertSame(0, $this->completedGameCount($members[0], $session));

        $component->call('openResultForm', $game->id)
            ->set('setScores', [
                ['team_a_score' => 15, 'team_b_score' => 10],
                ['team_a_score' => 15, 'team_b_score' => 9],
                ['team_a_score' => '', 'team_b_score' => ''],
            ])
            ->call('saveResult')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'status' => 'completed',
            'winner_team' => 'A',
            'team_a_score' => null,
            'team_b_score' => null,
        ]);
        $this->assertDatabaseHas('game_sets', ['game_id' => $game->id, 'set_number' => 1, 'team_a_score' => 15, 'team_b_score' => 10, 'winner_team' => 'A']);
        $this->assertDatabaseHas('game_sets', ['game_id' => $game->id, 'set_number' => 2, 'team_a_score' => 15, 'team_b_score' => 9, 'winner_team' => 'A']);
        $this->assertNotNull($game->refresh()->completed_at);
        $this->assertSame(1, $this->completedGameCount($members[0], $session));
        $this->assertSame(0, $this->completedGameCount($members[4], $session));

        $component->call('openResultForm', $game->id)
            ->assertSee('Tim A Memenangkan Game')
            ->assertSee($members[0]->name.' & '.$members[1]->name)
            ->assertDontSee('Set dimenangkan pada 15 poin');
    }

    public function test_admin_can_correct_completed_game_players_and_winner(): void
    {
        [$session, $members] = $this->sessionWithAttendees(5);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4));
        $game = Game::query()->firstOrFail();
        $component->call('startGame', $game->id)
            ->call('openResultForm', $game->id)
            ->set('setScores', [
                ['team_a_score' => 15, 'team_b_score' => 10],
                ['team_a_score' => 15, 'team_b_score' => 12],
                ['team_a_score' => '', 'team_b_score' => ''],
            ])
            ->call('saveResult');

        $component->call('openEditGameForm', $game->id)
            ->set('teamA', [$members[4]->id, $members[1]->id])
            ->set('teamB', [$members[2]->id, $members[3]->id])
            ->call('saveGame')
            ->assertHasNoErrors('game');
        $component->call('openResultForm', $game->id)
            ->set('setScores', [
                ['team_a_score' => 10, 'team_b_score' => 15],
                ['team_a_score' => 12, 'team_b_score' => 15],
                ['team_a_score' => '', 'team_b_score' => ''],
            ])
            ->call('saveResult')
            ->assertHasNoErrors();

        $this->assertSame(0, $this->completedGameCount($members[0], $session));
        $this->assertSame(1, $this->completedGameCount($members[4], $session));
        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'winner_team' => 'B',
            'team_a_score' => null,
            'team_b_score' => null,
        ]);
        $this->assertDatabaseCount('game_sets', 2);
    }

    public function test_admin_can_choose_two_sets_of_fifteen_and_the_result_may_draw(): void
    {
        [$session, $members] = $this->sessionWithAttendees(16);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4), true, Game::FORMAT_ROTATION);
        $game = Game::query()->firstOrFail();

        $this->assertSame(Game::FORMAT_ROTATION, $game->game_format);
        $this->assertSame(15, $game->point_target);

        $component->call('startGame', $game->id)
            ->call('openResultForm', $game->id)
            ->set('setScores', [
                ['team_a_score' => 15, 'team_b_score' => 10],
                ['team_a_score' => 9, 'team_b_score' => 15],
            ])
            ->call('saveResult')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'completed', 'winner_team' => null]);
        $this->assertDatabaseCount('game_sets', 2);
        $this->assertSame(1, $this->completedGameCount($members[0], $session));

        $component->call('openResultForm', $game->id)
            ->assertSee('Game Berakhir Seri');
    }

    public function test_game_format_must_be_selected_by_admin(): void
    {
        [$session, $members] = $this->sessionWithAttendees(8);
        $component = $this->sessionComponent($session);

        $component
            ->call('openCreateGameForm')
            ->assertSet('gameFormat', '')
            ->set('teamA', [$members[0]->id, $members[1]->id])
            ->set('teamB', [$members[2]->id, $members[3]->id])
            ->call('saveGame')
            ->assertHasErrors('gameFormat');

        $this->assertDatabaseCount('games', 0);
    }

    public function test_best_of_three_requires_a_rubber_set_only_after_one_all(): void
    {
        [$session, $members] = $this->sessionWithAttendees(8);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4));
        $game = Game::query()->firstOrFail();

        $component->call('startGame', $game->id)
            ->call('openResultForm', $game->id)
            ->set('setScores', [
                ['team_a_score' => 15, 'team_b_score' => 10],
                ['team_a_score' => 11, 'team_b_score' => 15],
                ['team_a_score' => '', 'team_b_score' => ''],
            ])
            ->call('saveResult')
            ->assertHasErrors('result')
            ->set('setScores.2.team_a_score', 15)
            ->set('setScores.2.team_b_score', 12)
            ->call('saveResult')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'winner_team' => 'A']);
        $this->assertDatabaseCount('game_sets', 3);
    }

    public function test_best_of_three_accepts_deuce_scores_up_to_twenty_four_points(): void
    {
        [$session, $members] = $this->sessionWithAttendees(8);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4));
        $game = Game::query()->firstOrFail();

        $component->call('startGame', $game->id)
            ->call('openResultForm', $game->id)
            ->set('setScores', [
                ['team_a_score' => 16, 'team_b_score' => 14],
                ['team_a_score' => 23, 'team_b_score' => 24],
                ['team_a_score' => 24, 'team_b_score' => 22],
            ])
            ->call('saveResult')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'winner_team' => 'A']);
        $this->assertDatabaseCount('game_sets', 3);
    }

    public function test_one_set_format_declares_winner_after_one_set_of_twenty_one(): void
    {
        [$session, $members] = $this->sessionWithAttendees(8);
        $component = $this->sessionComponent($session);

        $component
            ->call('openCreateGameForm')
            ->set('gameFormat', Game::FORMAT_ONE_SET)
            ->set('teamA', [$members[0]->id, $members[1]->id])
            ->set('teamB', [$members[2]->id, $members[3]->id])
            ->call('saveGame')
            ->assertHasNoErrors('game');

        $game = Game::query()->firstOrFail();
        $this->assertSame(Game::FORMAT_ONE_SET, $game->game_format);
        $this->assertSame(21, $game->point_target);

        $component->call('startGame', $game->id)
            ->call('openResultForm', $game->id)
            ->assertSet('setScores', [
                ['team_a_score' => '', 'team_b_score' => ''],
            ])
            ->set('setScores.0.team_a_score', 22)
            ->set('setScores.0.team_b_score', 20)
            ->call('saveResult')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('games', [
            'id' => $game->id,
            'status' => 'completed',
            'winner_team' => 'A',
        ]);
        $this->assertDatabaseHas('game_sets', [
            'game_id' => $game->id,
            'set_number' => 1,
            'team_a_score' => 22,
            'team_b_score' => 20,
            'winner_team' => 'A',
        ]);
        $this->assertDatabaseCount('game_sets', 1);
    }

    public function test_best_of_three_rejects_invalid_deuce_scores(): void
    {
        [$session, $members] = $this->sessionWithAttendees(8);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4));
        $game = Game::query()->firstOrFail();

        $component->call('startGame', $game->id)
            ->call('openResultForm', $game->id)
            ->set('setScores', [
                ['team_a_score' => 15, 'team_b_score' => 14],
                ['team_a_score' => 15, 'team_b_score' => 10],
                ['team_a_score' => '', 'team_b_score' => ''],
            ])
            ->call('saveResult')
            ->assertHasErrors('setScores.0.team_a_score');

        $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'playing']);
        $this->assertDatabaseCount('game_sets', 0);
    }

    public function test_unplayed_list_contains_attendee_with_zero_completed_games(): void
    {
        [$session, $members] = $this->sessionWithAttendees(5);
        $game = Game::query()->create([
            'play_session_id' => $session->id,
            'game_number' => 1,
            'status' => 'completed',
            'winner_team' => 'A',
            'completed_at' => now(),
        ]);

        foreach (array_slice($members, 0, 4) as $index => $member) {
            GamePlayer::query()->create([
                'game_id' => $game->id,
                'member_id' => $member->id,
                'team' => $index < 2 ? 'A' : 'B',
                'slot' => ($index % 2) + 1,
            ]);
        }

        $this->sessionComponent($session)
            ->assertViewHas('unplayedPlayers', fn ($players): bool => $players->pluck('member.id')->contains($members[4]->id))
            ->assertViewHas('readyPlayers', fn ($players): bool => $players->pluck('member.id')->contains($members[0]->id));
    }

    public function test_player_rotation_shows_a_simple_rest_count(): void
    {
        [$session, $members] = $this->sessionWithAttendees(8);

        $createCompletedGame = function (int $number, array $players) use ($session): void {
            $game = Game::query()->create([
                'play_session_id' => $session->id,
                'game_number' => $number,
                'status' => 'completed',
                'winner_team' => 'A',
                'completed_at' => now(),
            ]);

            foreach ($players as $index => $member) {
                GamePlayer::query()->create([
                    'game_id' => $game->id,
                    'member_id' => $member->id,
                    'team' => $index < 2 ? 'A' : 'B',
                    'slot' => ($index % 2) + 1,
                ]);
            }
        };

        $createCompletedGame(1, [$members[0], $members[1], $members[2], $members[3]]);
        $createCompletedGame(2, [$members[4], $members[5], $members[6], $members[7]]);
        $createCompletedGame(3, [$members[1], $members[2], $members[3], $members[4]]);
        $createCompletedGame(4, [$members[0], $members[5], $members[6], $members[7]]);
        $createCompletedGame(5, [$members[1], $members[2], $members[3], $members[4]]);

        $this->sessionComponent($session)
            ->assertViewHas('readyPlayers', function ($players) use ($members): bool {
                $row = $players->first(fn (array $player): bool => $player['member']->is($members[0]));

                return $row['completed_games'] === 2
                    && $row['last_game_number'] === 4
                    && $row['rested_games'] === 1;
            })
            ->assertSee('2× main · terakhir Game 4')
            ->assertSee('1 game')
            ->assertSee('Sudah di-skip')
            ->call('openCreateGameForm')
            ->assertSeeHtml($members[0]->name.' &mdash; '.ucfirst($members[0]->skill_level));
    }

    /** @return array{PlaySession, array<int, Member>} */
    private function sessionWithAttendees(int $count): array
    {
        $session = PlaySession::query()->create(['play_date' => '2026-10-10', 'status' => 'active']);
        $members = [];

        for ($index = 1; $index <= $count; $index++) {
            $member = Member::query()->create([
                'name' => 'Pemain '.$index,
                'skill_level' => $index % 3 === 0 ? 'pro' : ($index % 2 === 0 ? 'menengah' : 'pemula'),
            ]);
            PlaySessionMember::query()->create([
                'play_session_id' => $session->id,
                'member_id' => $member->id,
                'attended_at' => now(),
                'fee_amount' => 15000,
            ]);
            $members[] = $member;
        }

        return [$session, $members];
    }

    private function sessionComponent(PlaySession $session): Testable
    {
        return Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session]);
    }

    /** @param array<int, Member> $members */
    private function saveGame(
        Testable $component,
        array $members,
        bool $assertSuccess = true,
        string $format = Game::FORMAT_BEST_OF_THREE,
    ): Testable {
        $component
            ->call('openCreateGameForm')
            ->set('gameFormat', $format)
            ->set('teamA', [$members[0]->id, $members[1]->id])
            ->set('teamB', [$members[2]->id, $members[3]->id])
            ->call('saveGame');

        return $assertSuccess ? $component->assertHasNoErrors('game') : $component;
    }

    private function completedGameCount(Member $member, PlaySession $session): int
    {
        return GamePlayer::query()
            ->where('member_id', $member->id)
            ->whereHas('game', fn ($query) => $query
                ->where('play_session_id', $session->id)
                ->where('status', 'completed'))
            ->count();
    }
}
