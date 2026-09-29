<?php

namespace App\Livewire;

use App\Services\ReportService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Dashboard'])]
class Dashboard extends Component
{
    public function render(ReportService $reports): View
    {
        return view('livewire.dashboard', $reports->dashboardSummary());
    }
}
