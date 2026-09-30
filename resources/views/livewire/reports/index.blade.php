<div>
    <header class='page-header report-page-header'>
        <div><p class='eyebrow'>DATA OPERASIONAL</p><h1>Laporan</h1><p>Kalender transaksi, rincian sesi, dan laporan kas dari data {{ $appSettings->app_name }}.</p></div>
    </header>

    <nav class='report-tabs' aria-label='Jenis laporan'>
        <button class='{{ $activeTab === 'calendar' ? 'is-active' : '' }}' type='button' wire:click="selectTab('calendar')">Kalender Kas</button>
        <button class='{{ $activeTab === 'sessions' ? 'is-active' : '' }}' type='button' wire:click="selectTab('sessions')">Laporan Sesi</button>
        <button class='{{ $activeTab === 'cash' ? 'is-active' : '' }}' type='button' wire:click="selectTab('cash')">Laporan Kas</button>
    </nav>

    @if ($activeTab === 'calendar')
        <div class='calendar-report-layout'>
            <section class='report-panel cash-calendar-panel'>
                <header class='calendar-header'><button type='button' wire:click='previousMonth' aria-label='Bulan sebelumnya'>&larr;</button><h2>{{ $calendarTitle }}</h2><button type='button' wire:click='nextMonth' aria-label='Bulan berikutnya'>&rarr;</button></header>
                <div class='calendar-weekdays'>@foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $day)<span>{{ $day }}</span>@endforeach</div>
                <div class='cash-calendar'>
                    @foreach ($calendarDays as $day)
                        @php($dateKey = $day->toDateString())
                        @php($daySummary = $markedDays->get($dateKey))
                        <button class='calendar-day {{ $day->format('Y-m') !== $calendarMonth ? 'is-outside' : '' }} {{ $selectedDate === $dateKey ? 'is-selected' : '' }} {{ $daySummary ? 'has-transactions' : '' }}' type='button' wire:click="selectDate('{{ $dateKey }}')">
                            <span>{{ $day->day }}</span>
                            @if ($daySummary)<small>{{ $daySummary['count'] }} transaksi</small><i aria-hidden='true'></i>@endif
                        </button>
                    @endforeach
                </div>
            </section>

            <section class='report-panel selected-day-panel'>
                <header><div><p class='eyebrow'>RINCIAN TANGGAL</p><h2>{{ \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('d F Y') }}</h2></div></header>
                <div class='day-summary-grid'><div><span>Pemasukan</span><strong>Rp{{ number_format($selectedDay['income'], 0, ',', '.') }}</strong></div><div><span>Pengeluaran</span><strong>Rp{{ number_format($selectedDay['expense'], 0, ',', '.') }}</strong></div><div><span>Saldo hari itu</span><strong>Rp{{ number_format($selectedDay['balance'], 0, ',', '.') }}</strong></div></div>
                @if ($selectedDay['transactions']->isEmpty())
                    <div class='report-empty'>Tidak ada transaksi pada tanggal ini.</div>
                @else
                    <div class='day-transaction-list'>@foreach ($selectedDay['transactions'] as $transaction)<article><span class='category-icon' style='--category-color: {{ $transaction->cashCategory->color ?: '#65716a' }}'>{{ $transaction->cashCategory->icon ?: '•' }}</span><div><strong>{{ $transaction->cashCategory->name }}</strong><small>{{ $transaction->description ?: 'Tanpa keterangan' }}</small></div><b class='cash-amount--{{ $transaction->cashCategory->type }}'>{{ $transaction->cashCategory->type === 'income' ? '+' : '-' }}Rp{{ number_format($transaction->amount, 0, ',', '.') }}</b></article>@endforeach</div>
                @endif
            </section>
        </div>
    @else
        <form class='report-filter-bar' wire:submit='applyPeriod'>
            <label><span>Dari tanggal</span><input wire:model='dateFrom' type='date'>@error('dateFrom')<small>{{ $message }}</small>@enderror</label>
            <label><span>Sampai tanggal</span><input wire:model='dateTo' type='date'>@error('dateTo')<small>{{ $message }}</small>@enderror</label>
            <button class='secondary-action' type='submit'>Terapkan</button>
            <div class='report-export-actions'>
                <a href='{{ route('reports.'.($activeTab === 'cash' ? 'cash' : 'sessions').'.excel', ['from' => $dateFrom, 'to' => $dateTo]) }}'>Excel</a>
                <a href='{{ route('reports.'.($activeTab === 'cash' ? 'cash' : 'sessions').'.pdf', ['from' => $dateFrom, 'to' => $dateTo]) }}'>PDF</a>
            </div>
        </form>

        @if ($activeTab === 'cash')
            <section class='cash-summary-grid report-cash-summary'><article class='cash-summary cash-summary--income'><span>Total pemasukan</span><strong>Rp{{ number_format($cashReport['income'], 0, ',', '.') }}</strong></article><article class='cash-summary cash-summary--expense'><span>Total pengeluaran</span><strong>Rp{{ number_format($cashReport['expense'], 0, ',', '.') }}</strong></article><article class='cash-summary cash-summary--balance'><span>Saldo periode</span><strong>Rp{{ number_format($cashReport['balance'], 0, ',', '.') }}</strong></article></section>
            <section class='report-panel category-report-panel'><header><div><p class='eyebrow'>PER KATEGORI</p><h2>Rincian Kas</h2></div></header>
                @if ($cashReport['categoryTotals']->isEmpty())<div class='report-empty'>Belum ada transaksi pada periode ini.</div>@else<div class='category-total-grid'>@foreach ($cashReport['categoryTotals'] as $category)<article><span class='category-icon' style='--category-color: {{ $category->color ?: '#65716a' }}'>{{ $category->icon ?: '•' }}</span><div><strong>{{ $category->name }}</strong><small>{{ $category->type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</small></div><b>Rp{{ number_format($category->total, 0, ',', '.') }}</b></article>@endforeach</div>@endif
                @if ($cashReport['transactions']->isNotEmpty())<div class='report-table-wrap'><table class='report-table'><thead><tr><th>Tanggal</th><th>Kategori</th><th>Keterangan</th><th>Jenis</th><th>Nominal</th></tr></thead><tbody>@foreach ($cashReport['transactions'] as $transaction)<tr><td>{{ $transaction->transaction_date->translatedFormat('d M Y') }}</td><td>{{ $transaction->cashCategory->name }} @if($transaction->play_session_member_id)<span class='automatic-badge'>Otomatis</span>@endif</td><td>{{ $transaction->description ?: '-' }}</td><td>{{ $transaction->cashCategory->type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</td><td>Rp{{ number_format($transaction->amount, 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div>@endif
            </section>
        @else
            <section class='session-report-list'>
                @forelse ($sessionReports as $report)
                    <article class='report-panel session-report-card' wire:key='session-report-{{ $report['session']->id }}'>
                        <header><div><p class='eyebrow'>SESI MAIN</p><h2>{{ $report['session']->play_date->translatedFormat('d F Y') }}</h2></div><a href='{{ route('sessions.show', $report['session']) }}' wire:navigate>Lihat sesi</a></header>
                        <div class='session-report-summary'><div><span>Hadir</span><strong>{{ $report['attendance_count'] }}</strong></div><div><span>Sudah bayar</span><strong>{{ $report['paid_count'] }}</strong></div><div><span>Belum bayar</span><strong>{{ $report['unpaid_count'] }}</strong></div><div><span>Iuran diterima</span><strong>Rp{{ number_format($report['dues_received'], 0, ',', '.') }}</strong></div></div>
                        <div class='session-report-columns'>
                            <div><h3>Daftar game</h3>@forelse ($report['games'] as $game)@php($teamA = $game->gamePlayers->where('team', 'A')->pluck('member.name')->filter()->join(' & '))@php($teamB = $game->gamePlayers->where('team', 'B')->pluck('member.name')->filter()->join(' & '))<article class='report-game-row'><span>Game {{ $game->game_number }}</span><div><strong>{{ $teamA ?: 'Belum lengkap' }}</strong><small>vs</small><strong>{{ $teamB ?: 'Belum lengkap' }}</strong><small>{{ $game->scoreSummary() }}</small></div><b>{{ $game->resultLabel() }}</b></article>@empty<div class='report-empty report-empty--small'>Belum ada game.</div>@endforelse</div>
                            <div><h3>Jumlah main per member</h3>@forelse ($report['member_game_counts'] as $memberCount)<div class='member-game-count'><span>{{ $memberCount['member'] }}</span><strong>{{ $memberCount['count'] }} game</strong></div>@empty<div class='report-empty report-empty--small'>Belum ada data permainan.</div>@endforelse</div>
                        </div>
                    </article>
                @empty
                    <section class='report-panel report-empty report-empty--large'><h2>Belum ada sesi</h2><p>Tidak ada sesi main pada rentang tanggal ini.</p></section>
                @endforelse
            </section>
        @endif
    @endif
</div>
