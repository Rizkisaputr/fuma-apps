<?php

namespace App\Livewire\Sessions;

use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Member;
use App\Models\PlaySession;
use App\Models\PlaySessionMember;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Detail Sesi Main'])]
class Show extends Component
{
    public int $sessionId;

    public bool $showGameForm = false;

    public ?int $editingGameId = null;

    /** @var array<int, int|string> */
    public array $teamA = ['', ''];

    /** @var array<int, int|string> */
    public array $teamB = ['', ''];

    public ?int $resultGameId = null;

    public string $winnerTeam = '';

    public int|string $teamAScore = '';

    public int|string $teamBScore = '';

    public function mount(PlaySession $playSession): void
    {
        $this->sessionId = $playSession->id;
    }

    public function addAttendance(int $memberId): void
    {
        $this->resetErrorBag('attendance');
        $session = $this->playSession();

        if ($session->status === 'completed') {
            $this->addError('attendance', 'Sesi sudah selesai. Buka kembali sesi sebelum menambah absensi.');

            return;
        }

        $member = Member::query()
            ->where('is_active', true)
            ->findOrFail($memberId);

        if (PlaySessionMember::query()
            ->where('play_session_id', $session->id)
            ->where('member_id', $member->id)
            ->exists()) {
            $this->addError('attendance', 'Member sudah tercatat hadir pada sesi ini.');

            return;
        }

        try {
            PlaySessionMember::query()->create([
                'play_session_id' => $session->id,
                'member_id' => $member->id,
                'attended_at' => now(),
                'fee_amount' => $session->fee_amount,
            ]);
        } catch (QueryException) {
            $this->addError('attendance', 'Member sudah tercatat hadir pada sesi ini.');

            return;
        }

        session()->flash('session-message', $member->name.' berhasil ditandai hadir.');
    }

    public function removeAttendance(int $attendanceId): void
    {
        $this->resetErrorBag('attendance');
        $attendance = PlaySessionMember::query()
            ->where('play_session_id', $this->sessionId)
            ->findOrFail($attendanceId);

        $hasPayment = $attendance->paid_at !== null || $attendance->cashTransaction()->exists();
        $hasGame = GamePlayer::query()
            ->where('member_id', $attendance->member_id)
            ->whereHas('game', fn ($query) => $query->where('play_session_id', $this->sessionId))
            ->exists();

        if ($hasPayment || $hasGame) {
            $this->addError(
                'attendance',
                'Absensi tidak dapat dihapus karena sudah terhubung ke pembayaran atau game.',
            );

            return;
        }

        $memberName = $attendance->member?->name ?? 'Member';
        $attendance->delete();

        session()->flash('session-message', 'Absensi '.$memberName.' berhasil dikoreksi.');
    }

    public function markAsPaid(int $attendanceId): void
    {
        $this->resetErrorBag('payment');

        DB::transaction(function () use ($attendanceId): void {
            $attendance = PlaySessionMember::query()
                ->with(['member', 'playSession'])
                ->where('play_session_id', $this->sessionId)
                ->lockForUpdate()
                ->findOrFail($attendanceId);
            $category = CashCategory::query()
                ->where('code', 'dues')
                ->where('type', 'income')
                ->where('is_active', true)
                ->first();

            if (! $category) {
                $this->addError('payment', 'Kategori Iuran aktif tidak ditemukan. Jalankan seeder kategori kas.');

                return;
            }

            $existingTransaction = CashTransaction::query()
                ->where('play_session_member_id', $attendance->id)
                ->lockForUpdate()
                ->first();

            if ($existingTransaction && (
                $existingTransaction->cash_category_id !== $category->id
                || $existingTransaction->play_session_id !== $attendance->play_session_id
            )) {
                $this->addError('payment', 'Pembayaran tidak dapat diproses karena absensi terhubung ke transaksi kas yang tidak sesuai.');

                return;
            }

            if (! $existingTransaction) {
                CashTransaction::query()->create([
                    'cash_category_id' => $category->id,
                    'play_session_id' => $attendance->play_session_id,
                    'play_session_member_id' => $attendance->id,
                    'transaction_date' => $attendance->playSession->play_date,
                    'amount' => $attendance->fee_amount,
                    'description' => 'Iuran '.$attendance->member?->name,
                ]);
            }

            if ($attendance->paid_at === null) {
                $attendance->update(['paid_at' => now()]);
            }
        });

        if (! $this->getErrorBag()->has('payment')) {
            session()->flash('session-message', 'Pembayaran iuran berhasil dicatat.');
        }
    }

    public function cancelPayment(int $attendanceId): void
    {
        $this->resetErrorBag('payment');

        DB::transaction(function () use ($attendanceId): void {
            $attendance = PlaySessionMember::query()
                ->where('play_session_id', $this->sessionId)
                ->lockForUpdate()
                ->findOrFail($attendanceId);
            $transaction = CashTransaction::query()
                ->with('cashCategory')
                ->where('play_session_member_id', $attendance->id)
                ->lockForUpdate()
                ->first();

            if ($transaction && (
                $transaction->cashCategory?->code !== 'dues'
                || $transaction->cashCategory?->type !== 'income'
                || $transaction->play_session_id !== $attendance->play_session_id
            )) {
                $this->addError('payment', 'Pembayaran tidak dapat dibatalkan karena transaksi terkait bukan transaksi iuran otomatis.');

                return;
            }

            $transaction?->delete();
            $attendance->update(['paid_at' => null]);
        });

        if (! $this->getErrorBag()->has('payment')) {
            session()->flash('session-message', 'Tanda bayar dan transaksi iuran berhasil dibatalkan.');
        }
    }

    public function openCreateGameForm(): void
    {
        $this->resetGameForm();
        $this->showGameForm = true;
    }

    public function openEditGameForm(int $gameId): void
    {
        $game = $this->sessionGame($gameId);
        $players = $game->gamePlayers()->orderBy('team')->orderBy('slot')->get();

        $this->editingGameId = $game->id;
        $this->teamA = $players->where('team', 'A')->pluck('member_id')->values()->all();
        $this->teamB = $players->where('team', 'B')->pluck('member_id')->values()->all();
        $this->showGameForm = true;
        $this->resetErrorBag('game');
    }

    public function closeGameForm(): void
    {
        $this->resetGameForm();
    }

    public function saveGame(): void
    {
        $playerIds = $this->validatedGamePlayerIds();

        if ($playerIds === null) {
            return;
        }

        DB::transaction(function () use ($playerIds): void {
            $session = PlaySession::query()->lockForUpdate()->findOrFail($this->sessionId);

            if ($this->editingGameId === null && $session->status === 'completed') {
                $this->addError('game', 'Sesi selesai tidak dapat menerima game baru. Buka kembali sesi terlebih dahulu.');

                return;
            }

            $game = $this->editingGameId === null
                ? Game::query()->create([
                    'play_session_id' => $session->id,
                    'game_number' => ((int) Game::query()->where('play_session_id', $session->id)->max('game_number')) + 1,
                    'status' => 'waiting',
                ])
                : Game::query()
                    ->where('play_session_id', $session->id)
                    ->lockForUpdate()
                    ->findOrFail($this->editingGameId);

            $conflictingPlayer = GamePlayer::query()
                ->whereIn('member_id', $playerIds)
                ->where('game_id', '!=', $game->id)
                ->whereHas('game', fn ($query) => $query
                    ->where('play_session_id', $session->id)
                    ->where('status', 'playing'))
                ->exists();

            if ($conflictingPlayer) {
                if ($this->editingGameId === null) {
                    $game->delete();
                }

                $this->addError('game', 'Pemain yang sedang bermain tidak dapat dimasukkan ke game lain.');

                return;
            }

            $game->gamePlayers()->delete();

            foreach (['A' => array_slice($playerIds, 0, 2), 'B' => array_slice($playerIds, 2, 2)] as $team => $members) {
                foreach ($members as $slot => $memberId) {
                    GamePlayer::query()->create([
                        'game_id' => $game->id,
                        'member_id' => $memberId,
                        'team' => $team,
                        'slot' => $slot + 1,
                    ]);
                }
            }
        });

        if ($this->getErrorBag()->has('game')) {
            return;
        }

        session()->flash(
            'session-message',
            $this->editingGameId === null ? 'Game baru ditambahkan ke antrean.' : 'Susunan pemain berhasil dikoreksi.',
        );
        $this->resetGameForm();
    }

    public function startGame(int $gameId): void
    {
        $this->resetErrorBag('game');

        DB::transaction(function () use ($gameId): void {
            $session = PlaySession::query()->lockForUpdate()->findOrFail($this->sessionId);
            $game = Game::query()
                ->where('play_session_id', $session->id)
                ->lockForUpdate()
                ->findOrFail($gameId);

            if ($session->status === 'completed') {
                $this->addError('game', 'Buka kembali sesi sebelum memulai game.');

                return;
            }

            if ($game->status !== 'waiting') {
                $this->addError('game', 'Hanya game yang menunggu yang dapat dimulai.');

                return;
            }

            if ($game->gamePlayers()->count() !== 4) {
                $this->addError('game', 'Game harus memiliki tepat empat pemain sebelum dimulai.');

                return;
            }

            if (Game::query()
                ->where('play_session_id', $session->id)
                ->where('status', 'playing')
                ->whereKeyNot($game->id)
                ->exists()) {
                $this->addError('game', 'Lapangan sedang dipakai. Selesaikan game aktif terlebih dahulu.');

                return;
            }

            $game->update(['status' => 'playing', 'started_at' => now()]);

            if ($session->status === 'planned') {
                $session->update(['status' => 'active']);
            }
        });

        if (! $this->getErrorBag()->has('game')) {
            session()->flash('session-message', 'Game mulai dimainkan.');
        }
    }

    public function openResultForm(int $gameId): void
    {
        $game = $this->sessionGame($gameId);

        if (! in_array($game->status, ['playing', 'completed'], true)) {
            $this->addError('game', 'Hasil hanya dapat diisi untuk game yang sedang bermain atau sudah selesai.');

            return;
        }

        $this->resultGameId = $game->id;
        $this->winnerTeam = $game->winner_team ?? '';
        $this->teamAScore = $game->team_a_score ?? '';
        $this->teamBScore = $game->team_b_score ?? '';
        $this->resetErrorBag('result');
    }

    public function closeResultForm(): void
    {
        $this->resetResultForm();
    }

    public function saveResult(): void
    {
        $validated = $this->validate([
            'winnerTeam' => ['required', 'in:A,B'],
            'teamAScore' => ['nullable', 'integer', 'min:0', 'max:999'],
            'teamBScore' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'winnerTeam.required' => 'Pilih Tim A atau Tim B sebagai pemenang.',
            'winnerTeam.in' => 'Pilihan pemenang tidak valid.',
            'teamAScore.integer' => 'Skor Tim A harus berupa angka.',
            'teamBScore.integer' => 'Skor Tim B harus berupa angka.',
        ]);

        $game = $this->sessionGame($this->resultGameId ?? 0);

        if (! in_array($game->status, ['playing', 'completed'], true)) {
            $this->addError('result', 'Game belum dimulai sehingga belum dapat diselesaikan.');

            return;
        }

        $wasCompleted = $game->status === 'completed';
        $game->update([
            'status' => 'completed',
            'winner_team' => $validated['winnerTeam'],
            'team_a_score' => $validated['teamAScore'] === '' ? null : $validated['teamAScore'],
            'team_b_score' => $validated['teamBScore'] === '' ? null : $validated['teamBScore'],
            'completed_at' => $game->completed_at ?? now(),
        ]);

        session()->flash(
            'session-message',
            $wasCompleted ? 'Hasil game berhasil dikoreksi.' : 'Game selesai dan hasil berhasil disimpan.',
        );
        $this->resetResultForm();
    }

    public function startSession(): void
    {
        $session = $this->playSession();

        if ($session->status !== 'planned') {
            $this->addError('status', 'Hanya sesi terencana yang dapat dimulai.');

            return;
        }

        $session->update(['status' => 'active']);
        session()->flash('session-message', 'Sesi sekarang berstatus aktif.');
    }

    public function completeSession(): void
    {
        $session = $this->playSession();

        if ($session->status === 'completed') {
            $this->addError('status', 'Sesi ini sudah selesai.');

            return;
        }

        if ($session->games()->where('status', 'playing')->exists()) {
            $this->addError('status', 'Selesaikan game yang sedang berjalan sebelum menyelesaikan sesi.');

            return;
        }

        $session->update(['status' => 'completed']);
        session()->flash('session-message', 'Sesi berhasil diselesaikan dan absensi dikunci.');
    }

    public function reopenSession(): void
    {
        $session = $this->playSession();

        if ($session->status !== 'completed') {
            $this->addError('status', 'Hanya sesi selesai yang dapat dibuka kembali.');

            return;
        }

        $session->update(['status' => 'active']);
        session()->flash('session-message', 'Sesi dibuka kembali. Absensi baru dapat ditambahkan.');
    }

    private function playSession(): PlaySession
    {
        return PlaySession::query()->findOrFail($this->sessionId);
    }

    private function sessionGame(int $gameId): Game
    {
        return Game::query()
            ->where('play_session_id', $this->sessionId)
            ->findOrFail($gameId);
    }

    /** @return array<int, int>|null */
    private function validatedGamePlayerIds(): ?array
    {
        $this->resetErrorBag('game');

        $this->validate([
            'teamA' => ['required', 'array', 'size:2'],
            'teamA.*' => ['required', 'integer'],
            'teamB' => ['required', 'array', 'size:2'],
            'teamB.*' => ['required', 'integer'],
        ], [
            'teamA.size' => 'Tim A harus berisi tepat dua pemain.',
            'teamB.size' => 'Tim B harus berisi tepat dua pemain.',
            'teamA.*.required' => 'Pilih dua pemain untuk Tim A.',
            'teamB.*.required' => 'Pilih dua pemain untuk Tim B.',
        ]);

        $playerIds = array_map('intval', [...$this->teamA, ...$this->teamB]);

        if (count(array_unique($playerIds)) !== 4) {
            $this->addError('game', 'Empat slot harus diisi oleh empat pemain yang berbeda.');

            return null;
        }

        $attendedCount = PlaySessionMember::query()
            ->where('play_session_id', $this->sessionId)
            ->whereIn('member_id', $playerIds)
            ->distinct()
            ->count('member_id');

        if ($attendedCount !== 4) {
            $this->addError('game', 'Semua pemain game harus tercatat hadir pada sesi ini.');

            return null;
        }

        return $playerIds;
    }

    private function resetGameForm(): void
    {
        $this->showGameForm = false;
        $this->editingGameId = null;
        $this->teamA = ['', ''];
        $this->teamB = ['', ''];
        $this->resetErrorBag('game');
        $this->resetValidation(['teamA', 'teamB']);
    }

    private function resetResultForm(): void
    {
        $this->resultGameId = null;
        $this->winnerTeam = '';
        $this->teamAScore = '';
        $this->teamBScore = '';
        $this->resetErrorBag('result');
        $this->resetValidation(['winnerTeam', 'teamAScore', 'teamBScore']);
    }

    public function render(): View
    {
        $session = PlaySession::query()
            ->withCount('sessionMembers')
            ->findOrFail($this->sessionId);
        $attendances = PlaySessionMember::query()
            ->with('member')
            ->where('play_session_id', $session->id)
            ->orderBy('attended_at')
            ->get();
        $games = Game::query()
            ->with(['gamePlayers' => fn ($query) => $query->with('member')->orderBy('team')->orderBy('slot')])
            ->where('play_session_id', $session->id)
            ->orderBy('game_number')
            ->get();
        $completedCounts = GamePlayer::query()
            ->select('member_id', DB::raw('COUNT(*) as completed_games'))
            ->whereHas('game', fn ($query) => $query
                ->where('play_session_id', $session->id)
                ->where('status', 'completed'))
            ->groupBy('member_id')
            ->pluck('completed_games', 'member_id');
        $playingIds = $games
            ->where('status', 'playing')
            ->flatMap->gamePlayers
            ->pluck('member_id')
            ->unique();
        $waitingIds = $games
            ->where('status', 'waiting')
            ->flatMap->gamePlayers
            ->pluck('member_id')
            ->unique();
        $playerRows = $attendances->map(fn (PlaySessionMember $attendance): array => [
            'member' => $attendance->member,
            'completed_games' => (int) ($completedCounts[$attendance->member_id] ?? 0),
        ]);

        return view('livewire.sessions.show', [
            'playSession' => $session,
            'attendances' => $attendances,
            'attendedMembers' => $attendances->pluck('member')->filter(),
            'currentGame' => $games->firstWhere('status', 'playing'),
            'waitingGames' => $games->where('status', 'waiting')->values(),
            'completedGames' => $games->where('status', 'completed')->sortByDesc('game_number')->values(),
            'playingPlayers' => $this->playersIn($playerRows, $playingIds),
            'waitingPlayers' => $this->playersIn($playerRows, $waitingIds->diff($playingIds)),
            'unplayedPlayers' => $playerRows->filter(fn (array $row): bool => $row['completed_games'] === 0
                && ! $playingIds->contains($row['member']->id)
                && ! $waitingIds->contains($row['member']->id))->values(),
            'readyPlayers' => $playerRows->filter(fn (array $row): bool => $row['completed_games'] > 0
                && ! $playingIds->contains($row['member']->id)
                && ! $waitingIds->contains($row['member']->id))->values(),
            'availableMembers' => Member::query()
                ->where('is_active', true)
                ->whereNotIn('id', $attendances->pluck('member_id'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * @param  Collection<int, array{member: Member, completed_games: int}>  $players
     * @param  Collection<int, int>  $memberIds
     * @return Collection<int, array{member: Member, completed_games: int}>
     */
    private function playersIn(Collection $players, Collection $memberIds): Collection
    {
        return $players->filter(fn (array $row): bool => $memberIds->contains($row['member']->id))->values();
    }
}
