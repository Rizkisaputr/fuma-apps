<x-layouts.app :title='$title'>
    <header class='page-header'>
        <div>
            <p class='eyebrow'>FUMA APPS</p>
            <h1>{{ $title }}</h1>
            <p>{{ $description }}</p>
        </div>
    </header>

    <section class='empty-panel'>
        <div class='empty-symbol' aria-hidden='true'>—</div>
        <div>
            <span class='empty-badge'>SEGERA TERSEDIA</span>
            <h2>{{ $emptyTitle }}</h2>
            <p>{{ $emptyMessage }}</p>
        </div>
    </section>
</x-layouts.app>
