<div>
    <header class='page-header'>
        <div><p class='eyebrow'>RINGKASAN</p><h1>Dashboard</h1><p>Selamat datang, {{ auth()->user()->name }}. Berikut kondisi operasional {{ $appSettings->app_name }} saat ini.</p></div>
        <div class='date-pill'>{{ now()->translatedFormat('l, d F Y') }}</div>
    </header>

    <section class='dashboard-stat-grid'>
        <article><span>Member aktif</span><strong>{{ $activeMembers }}</strong><small>member</small></article>
        <article><span>Sesi bulan ini</span><strong>{{ $monthSessions }}</strong><small>sesi</small></article>
        <article><span>Saldo kas</span><strong>Rp{{ number_format($balance, 0, ',', '.') }}</strong><small>saldo saat ini</small></article>
        <article><span>Belum bayar</span><strong>{{ $unpaidAttendances }}</strong><small>kehadiran</small></article>
    </section>

    <section class='dashboard-welcome dashboard-cash-card'>
        <div><span class='section-kicker'>KAS BULAN INI</span><h2>Rp{{ number_format($monthIncome - $monthExpense, 0, ',', '.') }}</h2><p>Pemasukan Rp{{ number_format($monthIncome, 0, ',', '.') }} dikurangi pengeluaran Rp{{ number_format($monthExpense, 0, ',', '.') }}.</p></div>
        <a class='dashboard-report-link' href='{{ route('reports.index') }}' wire:navigate>Lihat laporan</a>
    </section>

    <section class='section-block'>
        <div class='section-heading'><div><p class='eyebrow'>AKSES CEPAT</p><h2>Area pengelolaan</h2></div>@if($latestSession)<span class='honest-state'>Sesi terakhir {{ $latestSession->play_date->translatedFormat('d M Y') }} · {{ $latestSession->session_members_count }} hadir</span>@else<span class='honest-state'>Belum ada sesi</span>@endif</div>
        <div class='quick-grid'>
            <a class='quick-card' href='{{ route('sessions.index') }}' wire:navigate><x-nav-icon name='sessions' /><div><strong>Sesi Main</strong><span>Jadwal, kehadiran, dan game</span></div><span class='quick-arrow' aria-hidden='true'>&rarr;</span></a>
            <a class='quick-card' href='{{ route('members.index') }}' wire:navigate><x-nav-icon name='members' /><div><strong>Member</strong><span>{{ $activeMembers }} member aktif</span></div><span class='quick-arrow' aria-hidden='true'>&rarr;</span></a>
            <a class='quick-card' href='{{ route('cash.index') }}' wire:navigate><x-nav-icon name='cash' /><div><strong>Kas</strong><span>Saldo Rp{{ number_format($balance, 0, ',', '.') }}</span></div><span class='quick-arrow' aria-hidden='true'>&rarr;</span></a>
        </div>
    </section>
</div>
