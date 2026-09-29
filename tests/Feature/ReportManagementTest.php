<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Livewire\Reports\Index as ReportIndex;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Member;
use App\Models\PlaySession;
use App\Models\PlaySessionMember;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\CashCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
    }

    public function test_calendar_marks_transaction_dates_and_shows_selected_day_totals(): void
    {
        $this->cashFixture();

        Livewire::actingAs(User::factory()->create())
            ->test(ReportIndex::class)
            ->set('calendarMonth', '2026-09')
            ->call('selectDate', '2026-09-10')
            ->assertViewHas('markedDays', fn ($days) => $days->has('2026-09-10') && ! $days->has('2026-10-01'))
            ->assertViewHas('selectedDay', fn ($report) => $report['income'] === 250000
                && $report['expense'] === 50000
                && $report['balance'] === 200000
                && $report['transactions']->count() === 3);
    }

    public function test_session_report_uses_cash_transactions_for_dues_and_includes_game_details(): void
    {
        [$session] = $this->cashFixture();
        $report = app(ReportService::class)->sessionReport('2026-09-01', '2026-09-30')->first();

        $this->assertSame(4, $report['attendance_count']);
        $this->assertSame(1, $report['paid_count']);
        $this->assertSame(3, $report['unpaid_count']);
        $this->assertSame(50000, $report['dues_received']);
        $this->assertCount(1, $report['games']);
        $this->assertSame('A', $report['games']->first()->winner_team);
        $this->assertCount(4, $report['member_game_counts']);
        $this->assertSame($session->id, $report['session']->id);
    }

    public function test_cash_report_totals_match_transactions_and_respect_period(): void
    {
        $this->cashFixture();
        $reports = app(ReportService::class);
        $september = $reports->cashReport('2026-09-01', '2026-09-30');
        $october = $reports->cashReport('2026-10-01', '2026-10-31');

        $this->assertSame(250000, $september['income']);
        $this->assertSame(50000, $september['expense']);
        $this->assertSame(200000, $september['balance']);
        $this->assertSame(3, $september['transactions']->count());
        $this->assertSame(900000, $october['income']);
        $this->assertSame(1, $october['transactions']->count());
        $this->assertSame(
            $september['income'],
            (int) $september['transactions']->where('cashCategory.type', 'income')->sum('amount'),
        );
    }

    public function test_session_without_attendance_or_games_is_still_reported(): void
    {
        PlaySession::query()->create(['play_date' => '2026-09-20', 'fee_amount' => 20000]);

        Livewire::actingAs(User::factory()->create())
            ->test(ReportIndex::class)
            ->set('activeTab', 'sessions')
            ->set('dateFrom', '2026-09-01')
            ->set('dateTo', '2026-09-30')
            ->call('applyPeriod')
            ->assertSee('20 September 2026')
            ->assertSee('Belum ada game')
            ->assertSee('Belum ada data permainan');
    }

    public function test_excel_and_pdf_exports_use_the_requested_period_and_filename(): void
    {
        $this->cashFixture();
        $user = User::factory()->create();
        $cashExcel = $this->actingAs($user)->get('/laporan/kas.xlsx?from=2026-09-01&to=2026-09-30');
        $cashExcel->assertOk()->assertDownload('laporan-kas-2026-09-01_sampai_2026-09-30.xlsx');
        $cashWorkbook = IOFactory::load($cashExcel->baseResponse->getFile()->getPathname());
        $this->assertSame(250000, (int) $cashWorkbook->getSheetByName('Ringkasan')->getCell('B5')->getValue());
        $this->assertSame(8, $cashWorkbook->getSheetByName('Transaksi')->getHighestRow());
        $cashWorkbook->disconnectWorksheets();

        $sessionExcel = $this->actingAs($user)->get('/laporan/sesi.xlsx?from=2026-09-01&to=2026-09-30');
        $sessionExcel->assertOk()->assertDownload('laporan-sesi-2026-09-01_sampai_2026-09-30.xlsx');
        $sessionWorkbook = IOFactory::load($sessionExcel->baseResponse->getFile()->getPathname());
        $this->assertSame(6, $sessionWorkbook->getSheetByName('Ringkasan Sesi')->getHighestRow());
        $this->assertSame(50000, (int) $sessionWorkbook->getSheetByName('Ringkasan Sesi')->getCell('E6')->getValue());
        $sessionWorkbook->disconnectWorksheets();

        $this->actingAs($user)
            ->get('/laporan/kas.pdf?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertDownload('laporan-kas-2026-09-01_sampai_2026-09-30.pdf');
        $this->actingAs($user)
            ->get('/laporan/sesi.pdf?from=2026-09-01&to=2026-09-30')
            ->assertOk()
            ->assertDownload('laporan-sesi-2026-09-01_sampai_2026-09-30.pdf');
    }

    public function test_report_exports_require_login_and_validate_period(): void
    {
        foreach (['kas.xlsx', 'kas.pdf', 'sesi.xlsx', 'sesi.pdf'] as $file) {
            $this->get('/laporan/'.$file.'?from=2026-09-01&to=2026-09-30')->assertRedirect('/login');
        }

        $this->actingAs(User::factory()->create())
            ->get('/laporan/kas.xlsx?from=2026-09-30&to=2026-09-01')
            ->assertSessionHasErrors('to');
    }

    public function test_category_icon_and_color_can_change_without_changing_transaction_type(): void
    {
        $sponsorship = CashCategory::query()->where('name', 'Sponsorship')->firstOrFail();
        CashTransaction::query()->create([
            'cash_category_id' => $sponsorship->id,
            'transaction_date' => '2026-09-10',
            'amount' => 100000,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(SettingsIndex::class)
            ->set('categories.'.$sponsorship->id.'.name', 'Mitra')
            ->set('categories.'.$sponsorship->id.'.icon', '★')
            ->set('categories.'.$sponsorship->id.'.color', '#336699')
            ->call('saveCategories')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_categories', [
            'id' => $sponsorship->id,
            'name' => 'Mitra',
            'type' => 'income',
            'icon' => '★',
            'color' => '#336699',
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_category_id' => $sponsorship->id,
            'amount' => 100000,
        ]);
    }

    public function test_dashboard_displays_real_database_totals(): void
    {
        $this->travelTo('2026-09-15');
        $this->cashFixture();

        Livewire::actingAs(User::factory()->create())
            ->test(Dashboard::class)
            ->assertViewHas('activeMembers', 4)
            ->assertViewHas('monthSessions', 1)
            ->assertViewHas('monthIncome', 250000)
            ->assertViewHas('monthExpense', 50000)
            ->assertViewHas('balance', 1100000)
            ->assertViewHas('unpaidAttendances', 3);
    }

    /** @return array{PlaySession, array<int, Member>} */
    private function cashFixture(): array
    {
        $iuran = CashCategory::query()->where('name', 'Iuran')->firstOrFail();
        $sponsorship = CashCategory::query()->where('name', 'Sponsorship')->firstOrFail();
        $booking = CashCategory::query()->where('name', 'Booking Court')->firstOrFail();
        $session = PlaySession::query()->create(['play_date' => '2026-09-10', 'fee_amount' => 50000]);
        $members = collect(['Ari', 'Bima', 'Candra', 'Dewi'])->map(
            fn (string $name) => Member::query()->create(['name' => $name, 'skill_level' => 'menengah']),
        );
        $attendances = $members->map(fn (Member $member, int $index) => PlaySessionMember::query()->create([
            'play_session_id' => $session->id,
            'member_id' => $member->id,
            'attended_at' => '2026-09-10 19:00:00',
            'fee_amount' => 50000,
            'paid_at' => $index === 0 ? '2026-09-10 19:10:00' : null,
        ]));
        CashTransaction::query()->create([
            'cash_category_id' => $iuran->id,
            'play_session_id' => $session->id,
            'play_session_member_id' => $attendances->first()->id,
            'transaction_date' => '2026-09-10',
            'amount' => 50000,
        ]);
        CashTransaction::query()->create([
            'cash_category_id' => $sponsorship->id,
            'transaction_date' => '2026-09-10',
            'amount' => 200000,
        ]);
        CashTransaction::query()->create([
            'cash_category_id' => $booking->id,
            'transaction_date' => '2026-09-10',
            'amount' => 50000,
        ]);
        CashTransaction::query()->create([
            'cash_category_id' => $sponsorship->id,
            'transaction_date' => '2026-10-01',
            'amount' => 900000,
        ]);
        $game = Game::query()->create([
            'play_session_id' => $session->id,
            'game_number' => 1,
            'status' => 'completed',
            'winner_team' => 'A',
            'team_a_score' => 21,
            'team_b_score' => 17,
        ]);
        foreach ($members as $index => $member) {
            GamePlayer::query()->create([
                'game_id' => $game->id,
                'member_id' => $member->id,
                'team' => $index < 2 ? 'A' : 'B',
                'slot' => ($index % 2) + 1,
            ]);
        }
        PlaySession::query()->create(['play_date' => '2026-10-01', 'fee_amount' => 50000]);

        return [$session, $members->all()];
    }
}
