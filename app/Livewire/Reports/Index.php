<?php

namespace App\Livewire\Reports;

use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Laporan'])]
class Index extends Component
{
    public string $activeTab = 'calendar';

    public string $calendarMonth = '';

    public string $selectedDate = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->calendarMonth = now()->format('Y-m');
        $this->selectedDate = now()->toDateString();
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->endOfMonth()->toDateString();
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, ['calendar', 'sessions', 'cash'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function previousMonth(): void
    {
        $month = Carbon::createFromFormat('Y-m', $this->calendarMonth)->subMonth();
        $this->calendarMonth = $month->format('Y-m');
        $this->selectedDate = $month->startOfMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $month = Carbon::createFromFormat('Y-m', $this->calendarMonth)->addMonth();
        $this->calendarMonth = $month->format('Y-m');
        $this->selectedDate = $month->startOfMonth()->toDateString();
    }

    public function selectDate(string $date): void
    {
        $parsed = Carbon::createFromFormat('Y-m-d', $date);
        $this->selectedDate = $parsed->toDateString();

        if ($parsed->format('Y-m') !== $this->calendarMonth) {
            $this->calendarMonth = $parsed->format('Y-m');
        }
    }

    public function applyPeriod(): void
    {
        $this->validate([
            'dateFrom' => ['required', 'date'],
            'dateTo' => ['required', 'date', 'after_or_equal:dateFrom'],
        ], [
            'dateFrom.required' => 'Tanggal awal wajib diisi.',
            'dateTo.required' => 'Tanggal akhir wajib diisi.',
            'dateTo.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);
    }

    public function render(ReportService $reports): View
    {
        $calendarStart = Carbon::createFromFormat('Y-m', $this->calendarMonth)->startOfMonth();
        $gridStart = $calendarStart->copy()->startOfWeek(Carbon::MONDAY);
        $calendarDays = collect(range(0, 41))->map(fn (int $offset): Carbon => $gridStart->copy()->addDays($offset));
        $markedDays = $reports->calendarDays($this->calendarMonth);

        return view('livewire.reports.index', [
            'calendarTitle' => $calendarStart->translatedFormat('F Y'),
            'calendarDays' => $calendarDays,
            'markedDays' => $markedDays,
            'selectedDay' => $reports->cashReport($this->selectedDate, $this->selectedDate),
            'sessionReports' => $reports->sessionReport($this->dateFrom, $this->dateTo),
            'cashReport' => $reports->cashReport($this->dateFrom, $this->dateTo),
        ]);
    }
}
