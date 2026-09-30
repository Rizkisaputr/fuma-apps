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
                ['team_a_score' => 21, 'team_b_score' => 17],
                ['team_a_score' => 21, 'team_b_score' => 15],
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
        $this->assertDatabaseHas('game_sets', ['game_id' => $game->id, 'set_number' => 1, 'team_a_score' => 21, 'team_b_score' => 17, 'winner_team' => 'A']);
        $this->assertDatabaseHas('game_sets', ['game_id' => $game->id, 'set_number' => 2, 'team_a_score' => 21, 'team_b_score' => 15, 'winner_team' => 'A']);
        $this->assertNotNull($game->refresh()->completed_at);
        $this->assertSame(1, $this->completedGameCount($members[0], $session));
        $this->assertSame(0, $this->completedGameCount($members[4], $session));
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
                ['team_a_score' => 21, 'team_b_score' => 16],
                ['team_a_score' => 21, 'team_b_score' => 18],
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
                ['team_a_score' => 15, 'team_b_score' => 21],
                ['team_a_score' => 18, 'team_b_score' => 21],
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

    public function test_sixteen_attendees_default_to_two_sets_of_eleven_and_may_draw(): void
    {
        [$session, $members] = $this->sessionWithAttendees(16);
        $component = $this->sessionComponent($session);
        $this->saveGame($component, array_slice($members, 0, 4));
        $game = Game::query()->firstOrFail();

        $this->assertSame(Game::FORMAT_ROTATION, $game->game_format);
        $this->assertSame(11, $game->point_target);

        $component->call('startGame', $game->id)
            ->call('openResultForm', $game->id)
            ->set('setScores', [
                ['team_a_score' => 11, 'team_b_score' => 7],
                ['team_a_score' => 8, 'team_b_score' => 11],
            ])
            ->call('saveResult')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'completed', 'winner_team' => null]);
        $this->assertDatabaseCount('game_sets', 2);
        $this->assertSame(1, $this->completedGameCount($members[0], $session));
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
                ['team_a_score' => 21, 'team_b_score' => 15],
                ['team_a_score' => 17, 'team_b_score' => 21],
                ['team_a_score' => '', 'team_b_score' => ''],
            ])
            ->call('saveResult')
            ->assertHasErrors('result')
            ->set('setScores.2.team_a_score', 21)
            ->set('setScores.2.team_b_score', 19)
            ->call('saveResult')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'winner_team' => 'A']);
        $this->assertDatabaseCount('game_sets', 3);
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
    private function saveGame(Testable $component, array $members, bool $assertSuccess = true): Testable
    {
        $component
            ->call('openCreateGameForm')
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
