<?php

namespace App\Livewire\Cash;

use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'Kas'])]
class Index extends Component
{
    use WithPagination;

    public bool $showForm = false;

    public string $transactionType = 'income';

    public int|string $categoryId = '';

    public string $transactionDate = '';

    public string $amount = '';

    public string $description = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->transactionDate = now()->toDateString();
    }

    public function updatedTransactionType(): void
    {
        $this->categoryId = '';
        $this->resetValidation('categoryId');
    }

    public function openForm(): void
    {
        $this->resetTransactionForm();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetTransactionForm();
    }

    public function saveTransaction(): void
    {
        $this->amount = $this->normalizeMoney($this->amount);

        $validated = $this->validate([
            'transactionType' => ['required', Rule::in(['income', 'expense'])],
            'categoryId' => ['required', 'integer'],
            'transactionDate' => ['required', 'date'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], [
            'transactionType.in' => 'Jenis transaksi tidak valid.',
            'categoryId.required' => 'Kategori wajib dipilih.',
            'transactionDate.required' => 'Tanggal transaksi wajib diisi.',
            'transactionDate.date' => 'Tanggal transaksi tidak valid.',
            'amount.required' => 'Nominal wajib diisi.',
            'amount.integer' => 'Nominal harus berupa angka bulat.',
            'amount.min' => 'Nominal harus lebih dari nol.',
            'description.max' => 'Keterangan maksimal 1000 karakter.',
        ]);

        $allowedCodes = $validated['transactionType'] === 'income'
            ? ['sponsorship']
            : ['court_booking', 'shuttlecock', 'refreshment'];
        $category = CashCategory::query()
            ->whereKey($validated['categoryId'])
            ->where('type', $validated['transactionType'])
            ->where('is_active', true)
            ->whereIn('code', $allowedCodes)
            ->first();

        if (! $category) {
            $this->addError('categoryId', 'Kategori tidak sesuai dengan jenis transaksi yang dipilih.');

            return;
        }

        CashTransaction::query()->create([
            'cash_category_id' => $category->id,
            'transaction_date' => $validated['transactionDate'],
            'amount' => $validated['amount'],
            'description' => trim($validated['description']) ?: null,
        ]);

        session()->flash('cash-message', 'Transaksi kas berhasil dicatat.');
        $this->closeForm();
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->validate([
            'dateFrom' => ['nullable', 'date'],
            'dateTo' => ['nullable', 'date', 'after_or_equal:dateFrom'],
        ], [
            'dateFrom.date' => 'Tanggal awal tidak valid.',
            'dateTo.date' => 'Tanggal akhir tidak valid.',
            'dateTo.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['dateFrom', 'dateTo']);
        $this->resetValidation(['dateFrom', 'dateTo']);
        $this->resetPage();
    }

    private function resetTransactionForm(): void
    {
        $this->transactionType = 'income';
        $this->categoryId = '';
        $this->transactionDate = now()->toDateString();
        $this->amount = '';
        $this->description = '';
        $this->resetValidation(['transactionType', 'categoryId', 'transactionDate', 'amount', 'description']);
    }

    private function normalizeMoney(string $value): string
    {
        return str_replace('.', '', trim((string) $value));
    }

    private function validDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d') === $date;
        } catch (\Throwable) {
            return false;
        }
    }

    public function render(): View
    {
        $reports = app(ReportService::class);
        $from = $this->validDate($this->dateFrom) ? $this->dateFrom : null;
        $to = $this->validDate($this->dateTo) ? $this->dateTo : null;
        $totals = $reports->cashTotals($from, $to);

        return view('livewire.cash.index', [
            'transactions' => $reports->cashQuery($from, $to)
                ->with(['cashCategory', 'playSessionMember.member'])
                ->orderByDesc('transaction_date')
                ->orderByDesc('id')
                ->paginate(12),
            'categories' => CashCategory::query()
                ->where('is_active', true)
                ->where('type', $this->transactionType)
                ->whereIn('code', $this->transactionType === 'income'
                    ? ['sponsorship']
                    : ['court_booking', 'shuttlecock', 'refreshment'])
                ->orderBy('name')
                ->get(),
            'totalIncome' => $totals['income'],
            'totalExpense' => $totals['expense'],
            'balance' => $totals['balance'],
            'hasFilters' => $this->dateFrom !== '' || $this->dateTo !== '',
        ]);
    }
}
