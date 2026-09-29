<?php

namespace Tests\Feature;

use App\Livewire\Sessions\Index;
use App\Livewire\Sessions\Show;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Member;
use App\Models\PlaySession;
use App\Models\PlaySessionMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_pages_require_authentication(): void
    {
        $session = PlaySession::query()->create(['play_date' => '2026-09-28']);

        $this->get('/sesi-main')->assertRedirect('/login');
        $this->get('/sesi-main/'.$session->id)->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/sesi-main')
            ->assertOk()
            ->assertSee('Buat Sesi');
    }

    public function test_admin_can_create_session_with_defaults(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('openCreateForm')
            ->set('playDate', '2026-10-03')
            ->call('createSession')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('play_sessions', [
            'fee_amount' => 15000,
            'court_count' => 1,
            'status' => 'planned',
        ]);
        $this->assertTrue(PlaySession::query()->whereDate('play_date', '2026-10-03')->exists());
    }

    public function test_session_fee_is_copied_to_attendance_created_with_session(): void
    {
        $member = Member::query()->create(['name' => 'Ari', 'skill_level' => 'menengah']);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('openCreateForm')
            ->set('playDate', '2026-10-04')
            ->set('feeAmount', '20.000')
            ->set('courtCount', 2)
            ->set('selectedMemberIds', [$member->id])
            ->call('createSession')
            ->assertHasNoErrors();

        $session = PlaySession::query()->whereDate('play_date', '2026-10-04')->firstOrFail();

        $this->assertDatabaseHas('play_session_members', [
            'play_session_id' => $session->id,
            'member_id' => $member->id,
            'fee_amount' => 20000,
        ]);
        $this->assertNotNull($session->sessionMembers()->firstOrFail()->attended_at);
    }

    public function test_admin_can_add_late_attendance_with_session_fee_snapshot(): void
    {
        $session = PlaySession::query()->create([
            'play_date' => '2026-10-05',
            'fee_amount' => 18000,
            'status' => 'active',
        ]);
        $member = Member::query()->create(['name' => 'Bima', 'skill_level' => 'pemula']);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->call('addAttendance', $member->id)
            ->assertHasNoErrors();

        $attendance = PlaySessionMember::query()->firstOrFail();
        $this->assertSame(18000, $attendance->fee_amount);
        $this->assertNotNull($attendance->attended_at);
    }

    public function test_duplicate_attendance_is_rejected(): void
    {
        $session = PlaySession::query()->create(['play_date' => '2026-10-06']);
        $member = Member::query()->create(['name' => 'Candra', 'skill_level' => 'pro']);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->call('addAttendance', $member->id)
            ->assertHasNoErrors()
            ->call('addAttendance', $member->id)
            ->assertHasErrors('attendance');

        $this->assertDatabaseCount('play_session_members', 1);
    }

    public function test_completed_session_blocks_attendance_until_admin_reopens_it(): void
    {
        $session = PlaySession::query()->create([
            'play_date' => '2026-10-07',
            'status' => 'completed',
        ]);
        $member = Member::query()->create(['name' => 'Dewi', 'skill_level' => 'menengah']);

        $component = Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->call('addAttendance', $member->id)
            ->assertHasErrors('attendance');

        $this->assertDatabaseCount('play_session_members', 0);

        $component->call('reopenSession')
            ->assertHasNoErrors('status')
            ->call('addAttendance', $member->id)
            ->assertHasNoErrors('attendance');

        $this->assertSame('active', $session->refresh()->status);
        $this->assertDatabaseCount('play_session_members', 1);
    }

    public function test_unlinked_attendance_can_be_corrected(): void
    {
        [$session, $member, $attendance] = $this->attendanceFixture();

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->call('removeAttendance', $attendance->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('play_session_members', ['id' => $attendance->id]);
        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }

    public function test_paid_attendance_cannot_be_removed(): void
    {
        [$session, , $attendance] = $this->attendanceFixture(['paid_at' => now()]);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->call('removeAttendance', $attendance->id)
            ->assertHasErrors('attendance');

        $this->assertDatabaseHas('play_session_members', ['id' => $attendance->id]);
    }

    public function test_attendance_linked_to_a_game_cannot_be_removed(): void
    {
        [$session, $member, $attendance] = $this->attendanceFixture();
        $game = Game::query()->create([
            'play_session_id' => $session->id,
            'game_number' => 1,
            'status' => 'waiting',
        ]);
        GamePlayer::query()->create([
            'game_id' => $game->id,
            'member_id' => $member->id,
            'team' => 'A',
            'slot' => 1,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(Show::class, ['playSession' => $session])
            ->call('removeAttendance', $attendance->id)
            ->assertHasErrors('attendance');

        $this->assertDatabaseHas('play_session_members', ['id' => $attendance->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{PlaySession, Member, PlaySessionMember}
     */
    private function attendanceFixture(array $overrides = []): array
    {
        $session = PlaySession::query()->create(['play_date' => '2026-10-08']);
        $member = Member::query()->create(['name' => 'Eka', 'skill_level' => 'pemula']);
        $attendance = PlaySessionMember::query()->create(array_merge([
            'play_session_id' => $session->id,
            'member_id' => $member->id,
            'attended_at' => now(),
            'fee_amount' => 15000,
        ], $overrides));

        return [$session, $member, $attendance];
    }
}
