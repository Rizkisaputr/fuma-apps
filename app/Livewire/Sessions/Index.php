<?php

namespace App\Livewire\Sessions;

use App\Models\ApplicationSetting;
use App\Models\Member;
use App\Models\PlaySession;
use App\Models\PlaySessionMember;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'Sesi Main'])]
class Index extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public string $sessionTypeFilter = '';

    public bool $showCreateForm = false;

    public string $playDate = '';

    public string $sessionType = PlaySession::TYPE_FUN_MATCH;

    public string $feeAmount = '15.000';

    public int $courtCount = 1;

    /** @var array<int, int|string> */
    public array $selectedMemberIds = [];

    public function mount(): void
    {
        $this->loadSessionDefaults();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSessionTypeFilter(): void
    {
        $this->resetPage();
    }

    public function openCreateForm(): void
    {
        $this->resetCreateForm();
        $this->playDate = today(config('app.display_timezone'))->format('Y-m-d');
        $this->showCreateForm = true;
    }

    public function closeCreateForm(): void
    {
        $this->showCreateForm = false;
        $this->resetCreateForm();
    }

    public function createSession(): void
    {
        $this->feeAmount = $this->normalizeMoney($this->feeAmount);

        $validated = $this->validate([
            'playDate' => ['required', 'date'],
            'sessionType' => ['required', Rule::in(array_keys(PlaySession::typeOptions()))],
            'feeAmount' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'courtCount' => ['required', 'integer', 'min:1', 'max:20'],
            'selectedMemberIds' => ['array'],
            'selectedMemberIds.*' => [
                'integer',
                'distinct',
                Rule::exists('members', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
        ], [
            'playDate.required' => 'Tanggal sesi wajib diisi.',
            'sessionType.required' => 'Jenis sesi wajib dipilih.',
            'sessionType.in' => 'Jenis sesi tidak valid.',
            'feeAmount.required' => 'Nominal iuran wajib diisi.',
            'feeAmount.min' => 'Nominal iuran minimal Rp1.',
            'courtCount.required' => 'Jumlah lapangan wajib diisi.',
            'courtCount.min' => 'Jumlah lapangan minimal 1.',
            'selectedMemberIds.*.exists' => 'Salah satu member tidak aktif atau tidak tersedia.',
            'selectedMemberIds.*.distinct' => 'Member yang sama tidak boleh dipilih dua kali.',
        ]);

        $memberIds = array_values(array_unique(array_map('intval', $validated['selectedMemberIds'] ?? [])));

        $session = DB::transaction(function () use ($validated, $memberIds): PlaySession {
            $session = PlaySession::query()->create([
                'play_date' => $validated['playDate'],
                'session_type' => $validated['sessionType'],
                'fee_amount' => $validated['feeAmount'],
                'court_count' => $validated['courtCount'],
                'status' => 'planned',
            ]);

            foreach ($memberIds as $memberId) {
                PlaySessionMember::query()->create([
                    'play_session_id' => $session->id,
                    'member_id' => $memberId,
                    'attended_at' => now(),
                    'fee_amount' => $session->fee_amount,
                ]);
            }

            return $session;
        });

        session()->flash('session-message', 'Sesi main berhasil dibuat.');
        $this->redirectRoute('sessions.show', ['playSession' => $session], navigate: true);
    }

    public function deleteSession(int $sessionId): void
    {
        $this->resetErrorBag('deleteSession');

        DB::transaction(function () use ($sessionId): void {
            $session = PlaySession::query()->lockForUpdate()->findOrFail($sessionId);
            $hasOperationalData = $session->games()->exists()
                || $session->cashTransactions()->exists()
                || $session->sessionMembers()->whereHas('cashTransaction')->exists()
                || $session->sessionMembers()->whereNotNull('paid_at')->exists();

            if ($hasOperationalData) {
                $this->addError(
                    'deleteSession',
                    'Sesi tidak dapat dihapus karena sudah memiliki game, pembayaran, atau transaksi kas.',
                );

                return;
            }

            $session->sessionMembers()->delete();
            $session->delete();
        });

        if (! $this->getErrorBag()->has('deleteSession')) {
            session()->flash('session-message', 'Sesi berhasil dihapus.');
            $this->resetPage();
        }
    }

    private function resetCreateForm(): void
    {
        $this->reset(['playDate', 'selectedMemberIds']);
        $this->sessionType = PlaySession::TYPE_FUN_MATCH;
        $this->loadSessionDefaults();
        $this->resetValidation();
    }

    private function loadSessionDefaults(): void
    {
        $settings = ApplicationSetting::current();
        $this->feeAmount = number_format($settings->default_fee_amount, 0, ',', '.');
        $this->courtCount = $settings->default_court_count;
    }

    private function normalizeMoney(string $value): string
    {
        return str_replace('.', '', trim((string) $value));
    }

    public function render(): View
    {
        $sessions = PlaySession::query()
            ->withCount('sessionMembers')
            ->when(in_array($this->statusFilter, ['planned', 'active', 'completed'], true), function (Builder $query): void {
                $query->where('status', $this->statusFilter);
            })
            ->when(array_key_exists($this->sessionTypeFilter, PlaySession::typeOptions()), function (Builder $query): void {
                $query->where('session_type', $this->sessionTypeFilter);
            })
            ->orderByDesc('play_date')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.sessions.index', [
            'sessions' => $sessions,
            'activeMembers' => Member::query()->where('is_active', true)->orderBy('name')->get(),
            'sessionTypes' => PlaySession::typeOptions(),
        ]);
    }
}
