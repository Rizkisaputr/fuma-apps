<?php

namespace Tests\Feature;

use App\Livewire\Members\Index;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Member;
use App\Models\PlaySession;
use App\Models\PlaySessionMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authenticated_admin_can_open_member_page(): void
    {
        $this->get('/member')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/member')
            ->assertOk()
            ->assertSee('Tambah Member');
    }

    public function test_admin_can_create_a_member(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('createMember')
            ->set('name', 'Raka Pratama')
            ->set('address', 'Jalan Melati 10, Bandung')
            ->set('gender', 'laki-laki')
            ->set('skillLevel', 'menengah')
            ->call('saveMember')
            ->assertHasNoErrors()
            ->assertSee('Member baru berhasil ditambahkan.');

        $this->assertDatabaseHas('members', [
            'name' => 'Raka Pratama',
            'address' => 'Jalan Melati 10, Bandung',
            'gender' => 'laki-laki',
            'skill_level' => 'menengah',
            'is_active' => true,
        ]);
    }

    public function test_member_form_validates_name_and_skill_level(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('createMember')
            ->set('name', '')
            ->set('address', '')
            ->set('gender', 'lainnya')
            ->set('skillLevel', 'mahir')
            ->call('saveMember')
            ->assertHasErrors([
                'name' => 'required',
                'address' => 'required',
                'gender' => 'in',
                'skillLevel' => 'in',
            ]);

        $this->assertDatabaseCount('members', 0);
    }

    public function test_admin_can_edit_a_member(): void
    {
        $member = Member::query()->create([
            'name' => 'Dina',
            'skill_level' => 'pemula',
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('editMember', $member->id)
            ->set('name', 'Dina Putri')
            ->set('address', 'Jalan Anggrek 7, Jakarta')
            ->set('gender', 'perempuan')
            ->set('skillLevel', 'pro')
            ->call('saveMember')
            ->assertHasNoErrors()
            ->assertSee('Data member berhasil diperbarui.');

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'name' => 'Dina Putri',
            'address' => 'Jalan Anggrek 7, Jakarta',
            'gender' => 'perempuan',
            'skill_level' => 'pro',
        ]);
    }

    public function test_search_and_filters_limit_visible_members(): void
    {
        Member::query()->create(['name' => 'Ani Pro', 'skill_level' => 'pro']);
        Member::query()->create(['name' => 'Budi Pemula', 'skill_level' => 'pemula']);
        Member::query()->create(['name' => 'Dedi Menengah', 'skill_level' => 'menengah']);
        Member::query()->create(['name' => 'Citra Nonaktif', 'skill_level' => 'pro', 'is_active' => false]);

        $component = Livewire::actingAs(User::factory()->create())->test(Index::class);

        $component->set('search', 'Ani')
            ->assertSee('Ani Pro')
            ->assertDontSee('Budi Pemula')
            ->call('resetFilters')
            ->set('levelFilter', 'pemula')
            ->assertSee('Budi Pemula')
            ->assertDontSee('Ani Pro')
            ->set('levelFilter', 'menengah')
            ->assertSee('Dedi Menengah')
            ->assertDontSee('Budi Pemula')
            ->set('levelFilter', '')
            ->set('statusFilter', 'inactive')
            ->assertSee('Citra Nonaktif')
            ->assertDontSee('Budi Pemula');
    }

    public function test_toggling_member_status_preserves_attendance_and_game_history(): void
    {
        $member = Member::query()->create(['name' => 'Tono', 'skill_level' => 'pemula']);
        $session = PlaySession::query()->create(['play_date' => '2026-09-28']);
        $attendance = PlaySessionMember::query()->create([
            'play_session_id' => $session->id,
            'member_id' => $member->id,
            'attended_at' => now(),
            'fee_amount' => 15000,
        ]);
        $game = Game::query()->create([
            'play_session_id' => $session->id,
            'game_number' => 1,
            'status' => 'completed',
        ]);
        $gamePlayer = GamePlayer::query()->create([
            'game_id' => $game->id,
            'member_id' => $member->id,
            'team' => 'A',
            'slot' => 1,
        ]);

        $component = Livewire::actingAs(User::factory()->create())->test(Index::class);
        $component->call('toggleActive', $member->id);

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'is_active' => false,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('play_session_members', ['id' => $attendance->id]);
        $this->assertDatabaseHas('game_players', ['id' => $gamePlayer->id]);

        $component->call('toggleActive', $member->id);
        $this->assertTrue($member->refresh()->is_active);
    }
}
