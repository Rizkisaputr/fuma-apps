<?php

namespace Tests\Feature;

use App\Livewire\Cash\Index as CashIndex;
use App\Livewire\Sessions\Show as SessionShow;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\Member;
use App\Models\PlaySession;
use App\Models\PlaySessionMember;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\CashCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CashManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CashCategorySeeder::class);
    }

    public function test_cash_page_requires_authenticated_admin(): void
    {
        $this->get('/kas')->assertRedirect('/login');

        $this->actingAs(User::factory()->create())
            ->get('/kas')
            ->assertOk()
            ->assertSee('Catat Transaksi');
    }

    public function test_attendance_can_be_paid_once_without_duplicate_transaction(): void
    {
        [$session, $attendance] = $this->attendanceFixture(17500, '2026-10-10');
        $component = Livewire::actingAs(User::factory()->create())
            ->test(SessionShow::class, ['playSession' => $session])
            ->call('markAsPaid', $attendance->id)
            ->assertHasNoErrors('payment');

        $this->assertNotNull($attendance->refresh()->paid_at);
        $this->assertDatabaseHas('cash_transactions', [
            'play_session_id' => $session->id,
            'play_session_member_id' => $attendance->id,
            'transaction_date' => '2026-10-10 00:00:00',
            'amount' => 17500,
            'description' => 'Iuran Ari',
        ]);

        $component->call('markAsPaid', $attendance->id)->assertHasNoErrors('payment');

        $this->assertDatabaseCount('cash_transactions', 1);
    }

    public function test_payment_can_be_cancelled_without_touching_manual_transaction(): void
    {
        [$session, $attendance] = $this->attendanceFixture();
        $sponsorship = CashCategory::query()->where('name', 'Sponsorship')->firstOrFail();
        $manual = CashTransaction::query()->create([
            'cash_category_id' => $sponsorship->id,
            'transaction_date' => '2026-10-09',
            'amount' => 500000,
            'description' => 'Sponsor utama',
        ]);
        $component = Livewire::actingAs(User::factory()->create())
            ->test(SessionShow::class, ['playSession' => $session])
            ->call('markAsPaid', $attendance->id);
        $automaticId = CashTransaction::query()
            ->where('play_session_member_id', $attendance->id)
            ->value('id');

        $component->call('cancelPayment', $attendance->id)->assertHasNoErrors('payment');

        $this->assertNull($attendance->refresh()->paid_at);
        $this->assertDatabaseMissing('cash_transactions', ['id' => $automaticId]);
        $this->assertDatabaseHas('cash_transactions', ['id' => $manual->id, 'amount' => 500000]);
    }

    public function test_automatic_dues_use_each_attendance_fee_snapshot(): void
    {
        [$firstSession, $firstAttendance] = $this->attendanceFixture(15000, '2026-10-11', 'Andi');
        [$secondSession, $secondAttendance] = $this->attendanceFixture(22500, '2026-10-12', 'Budi');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SessionShow::class, ['playSession' => $firstSession])
            ->call('markAsPaid', $firstAttendance->id);
        Livewire::actingAs($user)
            ->test(SessionShow::class, ['playSession' => $secondSession])
            ->call('markAsPaid', $secondAttendance->id);

        $this->assertDatabaseHas('cash_transactions', ['play_session_member_id' => $firstAttendance->id, 'amount' => 15000]);
        $this->assertDatabaseHas('cash_transactions', ['play_session_member_id' => $secondAttendance->id, 'amount' => 22500]);
    }

    public function test_duty_admin_is_exempt_from_dues_for_that_session(): void
    {
        [$session, $attendance] = $this->attendanceFixture();
        $component = Livewire::actingAs(User::factory()->create())
            ->test(SessionShow::class, ['playSession' => $session])
            ->call('toggleDutyAdmin', $attendance->id)
            ->assertHasNoErrors('payment')
            ->assertSee('Admin bertugas')
            ->assertSee('Bebas iuran');

        $this->assertTrue($attendance->refresh()->is_duty_admin);

        $component->call('markAsPaid', $attendance->id)
            ->assertHasErrors('payment');

        $this->assertNull($attendance->refresh()->paid_at);
        $this->assertDatabaseCount('cash_transactions', 0);
        $this->assertSame(0, app(ReportService::class)->dashboardSummary()['unpaidAttendances']);
    }

    public function test_paid_member_must_cancel_payment_before_becoming_duty_admin(): void
    {
        [$session, $attendance] = $this->attendanceFixture();
        $component = Livewire::actingAs(User::factory()->create())
            ->test(SessionShow::class, ['playSession' => $session])
            ->call('markAsPaid', $attendance->id)
            ->call('toggleDutyAdmin', $attendance->id)
            ->assertHasErrors('payment');

        $this->assertFalse($attendance->refresh()->is_duty_admin);
        $this->assertDatabaseCount('cash_transactions', 1);
    }

    public function test_admin_can_create_allowed_manual_transactions(): void
    {
        $sponsorship = CashCategory::query()->where('name', 'Sponsorship')->firstOrFail();
        $booking = CashCategory::query()->where('name', 'Booking Court')->firstOrFail();
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CashIndex::class)
            ->set('transactionType', 'income')
            ->set('categoryId', $sponsorship->id)
            ->set('transactionDate', '2026-10-13')
            ->set('amount', '300.000')
            ->set('description', 'Sponsor turnamen')
            ->call('saveTransaction')
            ->assertHasNoErrors();
        Livewire::actingAs($user)
            ->test(CashIndex::class)
            ->set('transactionType', 'expense')
            ->set('categoryId', $booking->id)
            ->set('transactionDate', '2026-10-13')
            ->set('amount', '125.000')
            ->call('saveTransaction')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_transactions', [
            'cash_category_id' => $sponsorship->id,
            'amount' => 300000,
            'play_session_member_id' => null,
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_category_id' => $booking->id,
            'amount' => 125000,
            'play_session_member_id' => null,
        ]);
    }

    public function test_admin_can_use_a_custom_cash_category(): void
    {
        $category = CashCategory::query()->create([
            'code' => 'custom_donation',
            'name' => 'Donasi',
            'type' => 'income',
            'is_active' => true,
        ]);

        Livewire::actingAs(User::factory()->create())
            ->test(CashIndex::class)
            ->set('transactionType', 'income')
            ->assertViewHas('categories', fn ($categories) => $categories->contains('id', $category->id))
            ->set('categoryId', $category->id)
            ->set('transactionDate', '2026-10-13')
            ->set('amount', '75.000')
            ->call('saveTransaction')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_transactions', [
            'cash_category_id' => $category->id,
            'amount' => 75000,
        ]);
    }

    public function test_manual_transaction_validates_positive_amount_and_matching_category(): void
    {
        $booking = CashCategory::query()->where('name', 'Booking Court')->firstOrFail();

        Livewire::actingAs(User::factory()->create())
            ->test(CashIndex::class)
            ->set('transactionType', 'income')
            ->set('categoryId', $booking->id)
            ->set('transactionDate', '2026-10-13')
            ->set('amount', 0)
            ->call('saveTransaction')
            ->assertHasErrors(['amount' => 'min']);

        Livewire::actingAs(User::factory()->create())
            ->test(CashIndex::class)
            ->set('transactionType', 'income')
            ->set('categoryId', $booking->id)
            ->set('transactionDate', '2026-10-13')
            ->set('amount', 10000)
            ->call('saveTransaction')
            ->assertHasErrors('categoryId');

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_cash_totals_and_date_filter_are_calculated_correctly(): void
    {
        $sponsorship = CashCategory::query()->where('name', 'Sponsorship')->firstOrFail();
        $booking = CashCategory::query()->where('name', 'Booking Court')->firstOrFail();

        foreach ([
            [$sponsorship->id, '2026-10-01', 500000],
            [$booking->id, '2026-10-02', 125000],
            [$sponsorship->id, '2026-11-01', 100000],
        ] as [$categoryId, $date, $amount]) {
            CashTransaction::query()->create([
                'cash_category_id' => $categoryId,
                'transaction_date' => $date,
                'amount' => $amount,
            ]);
        }

        Livewire::actingAs(User::factory()->create())
            ->test(CashIndex::class)
            ->assertViewHas('totalIncome', 600000)
            ->assertViewHas('totalExpense', 125000)
            ->assertViewHas('balance', 475000)
            ->set('dateFrom', '2026-10-01')
            ->set('dateTo', '2026-10-31')
            ->call('applyFilters')
            ->assertHasNoErrors()
            ->assertViewHas('totalIncome', 500000)
            ->assertViewHas('totalExpense', 125000)
            ->assertViewHas('balance', 375000)
            ->assertViewHas('transactions', fn ($transactions) => $transactions->total() === 2);
    }

    /** @return array{PlaySession, PlaySessionMember} */
    private function attendanceFixture(int $fee = 15000, string $date = '2026-10-10', string $name = 'Ari'): array
    {
        $session = PlaySession::query()->create(['play_date' => $date, 'fee_amount' => $fee]);
        $member = Member::query()->create(['name' => $name, 'skill_level' => 'menengah']);
        $attendance = PlaySessionMember::query()->create([
            'play_session_id' => $session->id,
            'member_id' => $member->id,
            'attended_at' => now(),
            'fee_amount' => $fee,
        ]);

        return [$session, $attendance];
    }
}
