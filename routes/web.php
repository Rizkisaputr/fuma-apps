<?php

use App\Http\Controllers\ReportExportController;
use App\Livewire\Auth\Login;
use App\Livewire\Cash\Index as CashIndex;
use App\Livewire\Dashboard;
use App\Livewire\Members\Index as MemberIndex;
use App\Livewire\Reports\Index as ReportIndex;
use App\Livewire\Sessions\Index as SessionIndex;
use App\Livewire\Sessions\Show as SessionShow;
use App\Livewire\Settings\Index as SettingIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->get('/login', Login::class)->name('login');

Route::middleware('auth')->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');
    Route::get('/sesi-main', SessionIndex::class)->name('sessions.index');
    Route::get('/sesi-main/{playSession}', SessionShow::class)->name('sessions.show');
    Route::get('/member', MemberIndex::class)->name('members.index');
    Route::get('/kas', CashIndex::class)->name('cash.index');
    Route::get('/laporan', ReportIndex::class)->name('reports.index');
    Route::get('/laporan/kas.xlsx', [ReportExportController::class, 'cashExcel'])->name('reports.cash.excel');
    Route::get('/laporan/kas.pdf', [ReportExportController::class, 'cashPdf'])->name('reports.cash.pdf');
    Route::get('/laporan/sesi.xlsx', [ReportExportController::class, 'sessionsExcel'])->name('reports.sessions.excel');
    Route::get('/laporan/sesi.pdf', [ReportExportController::class, 'sessionsPdf'])->name('reports.sessions.pdf');
    Route::get('/pengaturan', SettingIndex::class)->name('settings.index');

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
