<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\Member;
use App\Models\PlaySession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ReportService
{
    public function cashQuery(?string $from = null, ?string $to = null): Builder
    {
        return CashTransaction::query()
            ->when($from, fn (Builder $query) => $query->whereDate('transaction_date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('transaction_date', '<=', $to));
    }

    /** @return array{income: int, expense: int, balance: int} */
    public function cashTotals(?string $from = null, ?string $to = null): array
    {
        $totals = $this->cashQuery($from, $to)
            ->join('cash_categories', 'cash_categories.id', '=', 'cash_transactions.cash_category_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN cash_categories.type = 'income' THEN amount ELSE 0 END), 0) as income")
            ->selectRaw("COALESCE(SUM(CASE WHEN cash_categories.type = 'expense' THEN amount ELSE 0 END), 0) as expense")
            ->first();
        $income = (int) $totals->income;
        $expense = (int) $totals->expense;

        return ['income' => $income, 'expense' => $expense, 'balance' => $income - $expense];
    }

    /**
     * @return array{
     *   transactions: Collection<int, CashTransaction>,
     *   categoryTotals: Collection<int, object>,
     *   income: int,
     *   expense: int,
     *   balance: int
     * }
     */
    public function cashReport(string $from, string $to): array
    {
        $totals = $this->cashTotals($from, $to);

        return [
            'transactions' => $this->cashQuery($from, $to)
                ->with(['cashCategory', 'playSessionMember.member'])
                ->orderBy('transaction_date')
                ->orderBy('id')
                ->get(),
            'categoryTotals' => $this->cashQuery($from, $to)
                ->join('cash_categories', 'cash_categories.id', '=', 'cash_transactions.cash_category_id')
                ->selectRaw('cash_categories.id, cash_categories.name, cash_categories.type, cash_categories.icon, cash_categories.color, SUM(cash_transactions.amount) as total')
                ->groupBy('cash_categories.id', 'cash_categories.name', 'cash_categories.type', 'cash_categories.icon', 'cash_categories.color')
                ->orderBy('cash_categories.type')
                ->orderBy('cash_categories.name')
                ->get(),
            ...$totals,
        ];
    }

    /** @return Collection<string, array{income: int, expense: int, balance: int, count: int}> */
    public function calendarDays(string $month): Collection
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return $this->cashQuery($start->toDateString(), $end->toDateString())
            ->join('cash_categories', 'cash_categories.id', '=', 'cash_transactions.cash_category_id')
            ->selectRaw('transaction_date, COUNT(*) as transaction_count')
            ->selectRaw("SUM(CASE WHEN cash_categories.type = 'income' THEN amount ELSE 0 END) as income")
            ->selectRaw("SUM(CASE WHEN cash_categories.type = 'expense' THEN amount ELSE 0 END) as expense")
            ->groupBy('transaction_date')
            ->orderBy('transaction_date')
            ->get()
            ->mapWithKeys(fn ($row): array => [Carbon::parse($row->transaction_date)->toDateString() => [
                'income' => (int) $row->income,
                'expense' => (int) $row->expense,
                'balance' => (int) $row->income - (int) $row->expense,
                'count' => (int) $row->transaction_count,
            ]]);
    }

    /** @return Collection<int, array<string, mixed>> */
    public function sessionReport(string $from, string $to): Collection
    {
        return PlaySession::query()
            ->with([
                'sessionMembers.member',
                'cashTransactions.cashCategory',
                'games' => fn ($query) => $query
                    ->with([
                        'gamePlayers' => fn ($playerQuery) => $playerQuery->with('member')->orderBy('team')->orderBy('slot'),
                        'gameSets',
                    ])
                    ->orderBy('game_number'),
            ])
            ->whereDate('play_date', '>=', $from)
            ->whereDate('play_date', '<=', $to)
            ->orderBy('play_date')
            ->orderBy('id')
            ->get()
            ->map(function (PlaySession $session): array {
                $billableAttendances = $session->sessionMembers->where('is_duty_admin', false);
                $paid = $billableAttendances->whereNotNull('paid_at')->count();
                $gameCounts = $session->games
                    ->flatMap->gamePlayers
                    ->groupBy('member_id')
                    ->map(fn (Collection $players): array => [
                        'member' => $players->first()->member?->name ?? 'Member terhapus',
                        'count' => $players->count(),
                    ])
                    ->sortByDesc('count')
                    ->values();

                return [
                    'session' => $session,
                    'attendance_count' => $session->sessionMembers->count(),
                    'paid_count' => $paid,
                    'unpaid_count' => $billableAttendances->count() - $paid,
                    'duty_admin_count' => $session->sessionMembers->where('is_duty_admin', true)->count(),
                    'dues_received' => (int) $session->cashTransactions
                        ->filter(fn (CashTransaction $transaction): bool => $transaction->play_session_member_id !== null
                            && $transaction->cashCategory?->code === 'dues'
                            && $transaction->cashCategory?->type === 'income')
                        ->sum('amount'),
                    'attendances' => $session->sessionMembers,
                    'games' => $session->games,
                    'member_game_counts' => $gameCounts,
                ];
            });
    }

    /** @return array<string, int|PlaySession|null> */
    public function dashboardSummary(): array
    {
        $localNow = now(config('app.display_timezone'));
        $monthStart = $localNow->copy()->startOfMonth()->toDateString();
        $monthEnd = $localNow->copy()->endOfMonth()->toDateString();
        $monthCash = $this->cashTotals($monthStart, $monthEnd);
        $allCash = $this->cashTotals();

        return [
            'activeMembers' => Member::query()->where('is_active', true)->count(),
            'monthSessions' => PlaySession::query()->whereBetween('play_date', [$monthStart, $monthEnd])->count(),
            'monthIncome' => $monthCash['income'],
            'monthExpense' => $monthCash['expense'],
            'balance' => $allCash['balance'],
            'unpaidAttendances' => PlaySession::query()
                ->join('play_session_members', 'play_session_members.play_session_id', '=', 'play_sessions.id')
                ->whereNull('play_session_members.paid_at')
                ->where('play_session_members.is_duty_admin', false)
                ->count(),
            'latestSession' => PlaySession::query()->withCount('sessionMembers')->orderByDesc('play_date')->first(),
        ];
    }
}
