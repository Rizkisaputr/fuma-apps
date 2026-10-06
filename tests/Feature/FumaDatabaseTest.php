<?php

namespace Tests\Feature;

use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Member;
use App\Models\PlaySession;
use App\Models\PlaySessionMember;
use Database\Seeders\CashCategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FumaDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_models_have_expected_relationships_and_defaults(): void
    {
        $member = Member::create(['name' => 'Budi', 'skill_level' => 'pemula'])->refresh();
        $session = PlaySession::create(['play_date' => '2026-09-28'])->refresh();
        $attendance = PlaySessionMember::create([
            'play_session_id' => $session->id,
            'member_id' => $member->id,
            'attended_at' => now(),
            'fee_amount' => $session->fee_amount,
            'paid_at' => now(),
        ]);
        $game = Game::create([
            'play_session_id' => $session->id,
            'game_number' => 1,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        GamePlayer::create([
            'game_id' => $game->id,
            'member_id' => $member->id,
            'team' => 'A',
            'slot' => 1,
        ]);
        $category = CashCategory::create(['name' => 'Iuran', 'type' => 'income']);
        $transaction = CashTransaction::create([
            'cash_category_id' => $category->id,
            'play_session_id' => $session->id,
            'play_session_member_id' => $attendance->id,
            'transaction_date' => '2026-09-28',
            'amount' => 15000,
        ]);

        $this->assertTrue($member->is_active);
        $this->assertSame(15000, $session->fee_amount);
        $this->assertSame(1, $session->court_count);
        $this->assertSame('planned', $session->status);
        $this->assertTrue($attendance->isPaid());
        $this->assertTrue($session->members->contains($member));
        $this->assertTrue($member->games->contains($game));
        $this->assertTrue($game->players->contains($member));
        $this->assertTrue($attendance->cashTransaction->is($transaction));
        $this->assertTrue($transaction->cashCategory->is($category));
        $this->assertTrue($transaction->playSession->is($session));
    }

    public function test_members_use_soft_deletes(): void
    {
        $member = Member::create(['name' => 'Siti', 'skill_level' => 'pro']);
        $member->delete();

        $this->assertSoftDeleted($member);
        $this->assertNotNull(Member::withTrashed()->findOrFail($member->id)->deleted_at);
    }

    public function test_a_member_can_only_join_a_session_once(): void
    {
        $member = Member::create(['name' => 'Deni', 'skill_level' => 'pro']);
        $session = PlaySession::create(['play_date' => '2026-09-28']);
        $attributes = [
            'play_session_id' => $session->id,
            'member_id' => $member->id,
            'attended_at' => now(),
            'fee_amount' => 15000,
        ];
        PlaySessionMember::create($attributes);

        $this->expectException(QueryException::class);
        PlaySessionMember::create($attributes);
    }

    public function test_game_player_unique_constraints_are_enforced(): void
    {
        $session = PlaySession::create(['play_date' => '2026-09-28']);
        $game = Game::create(['play_session_id' => $session->id, 'game_number' => 1]);
        $first = Member::create(['name' => 'Ani', 'skill_level' => 'pemula']);
        $second = Member::create(['name' => 'Rudi', 'skill_level' => 'pro']);
        GamePlayer::create([
            'game_id' => $game->id,
            'member_id' => $first->id,
            'team' => 'A',
            'slot' => 1,
        ]);

        try {
            GamePlayer::create([
                'game_id' => $game->id,
                'member_id' => $first->id,
                'team' => 'B',
                'slot' => 1,
            ]);
            $this->fail('The same member was inserted twice in one game.');
        } catch (QueryException) {
            $this->assertDatabaseCount('game_players', 1);
        }

        $this->expectException(QueryException::class);
        GamePlayer::create([
            'game_id' => $game->id,
            'member_id' => $second->id,
            'team' => 'A',
            'slot' => 1,
        ]);
    }

    public function test_a_session_game_number_is_unique(): void
    {
        $session = PlaySession::create(['play_date' => '2026-09-28']);
        Game::create(['play_session_id' => $session->id, 'game_number' => 1]);

        $this->expectException(QueryException::class);
        Game::create(['play_session_id' => $session->id, 'game_number' => 1]);
    }

    public function test_an_attendance_fee_can_only_create_one_cash_transaction(): void
    {
        $member = Member::create(['name' => 'Tono', 'skill_level' => 'pemula']);
        $session = PlaySession::create(['play_date' => '2026-09-28']);
        $attendance = PlaySessionMember::create([
            'play_session_id' => $session->id,
            'member_id' => $member->id,
            'attended_at' => now(),
            'fee_amount' => 15000,
        ]);
        $category = CashCategory::create(['name' => 'Iuran', 'type' => 'income']);
        $attributes = [
            'cash_category_id' => $category->id,
            'play_session_id' => $session->id,
            'play_session_member_id' => $attendance->id,
            'transaction_date' => '2026-09-28',
            'amount' => 15000,
        ];
        CashTransaction::create($attributes);

        $this->expectException(QueryException::class);
        CashTransaction::create($attributes);
    }

    public function test_cash_category_seeder_is_idempotent(): void
    {
        $this->seed(CashCategorySeeder::class);
        $this->seed(CashCategorySeeder::class);

        $this->assertDatabaseCount('cash_categories', 7);
        $this->assertDatabaseHas('cash_categories', ['name' => 'Iuran', 'type' => 'income']);
        $this->assertDatabaseHas('cash_categories', ['name' => 'Refreshment', 'type' => 'expense']);
        $this->assertDatabaseHas('cash_categories', ['code' => 'other_income', 'name' => 'Lain-lain', 'type' => 'income']);
        $this->assertDatabaseHas('cash_categories', ['code' => 'other_expense', 'name' => 'Lain-lain', 'type' => 'expense']);
    }
}
