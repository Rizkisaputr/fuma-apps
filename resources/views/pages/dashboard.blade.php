<x-layouts.app title='Dashboard'>
    <header class='page-header'>
        <div>
            <p class='eyebrow'>RINGKASAN</p>
            <h1>Dashboard</h1>
            <p>Selamat datang, {{ auth()->user()->name }}. Kerangka admin FUMA sudah siap digunakan.</p>
        </div>
        <div class='date-pill'>{{ now(config('app.display_timezone'))->translatedFormat('l, d F Y') }}</div>
    </header>

    <section class='dashboard-welcome'>
        <div>
            <span class='section-kicker'>FUMA APPS</span>
            <h2>Operasional badminton, lebih tertata.</h2>
            <p>Modul sesi, member, game, dan kas akan diaktifkan bertahap. Saat ini Anda dapat menjelajahi kerangka setiap halaman.</p>
        </div>
        <span class='welcome-monogram' aria-hidden='true'>F</span>
    </section>

    <section class='section-block'>
        <div class='section-heading'>
            <div>
                <p class='eyebrow'>AKSES CEPAT</p>
                <h2>Area pengelolaan</h2>
            </div>
            <span class='honest-state'>Belum ada data operasional</span>
        </div>

        <div class='quick-grid'>
            <a class='quick-card' href='{{ route('sessions.index') }}' wire:navigate>
                <x-nav-icon name='sessions' />
                <div><strong>Sesi Main</strong><span>Jadwal dan kehadiran</span></div>
                <span class='quick-arrow' aria-hidden='true'>→</span>
            </a>
            <a class='quick-card' href='{{ route('members.index') }}' wire:navigate>
                <x-nav-icon name='members' />
                <div><strong>Member</strong><span>Data anggota FUMA</span></div>
                <span class='quick-arrow' aria-hidden='true'>→</span>
            </a>
            <a class='quick-card' href='{{ route('cash.index') }}' wire:navigate>
                <x-nav-icon name='cash' />
                <div><strong>Kas</strong><span>Pemasukan dan pengeluaran</span></div>
                <span class='quick-arrow' aria-hidden='true'>→</span>
            </a>
        </div>
    </section>

    <section class='empty-panel empty-panel--compact'>
        <div class='empty-symbol' aria-hidden='true'>—</div>
        <div>
            <h2>Aktivitas belum tersedia</h2>
            <p>Aktivitas terbaru akan tampil setelah fitur operasional mulai digunakan.</p>
        </div>
    </section>
</x-layouts.app>
