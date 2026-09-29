@props(['title'])

@php
    $navigation = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard'],
        ['label' => 'Sesi Main', 'route' => 'sessions.index', 'active' => 'sessions.*', 'icon' => 'sessions'],
        ['label' => 'Member', 'route' => 'members.index', 'active' => 'members.*', 'icon' => 'members'],
        ['label' => 'Kas', 'route' => 'cash.index', 'active' => 'cash.*', 'icon' => 'cash'],
        ['label' => 'Laporan', 'route' => 'reports.index', 'active' => 'reports.*', 'icon' => 'reports'],
        ['label' => 'Pengaturan', 'route' => 'settings.index', 'active' => 'settings.*', 'icon' => 'settings'],
    ];
@endphp

<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='utf-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <meta name='theme-color' content='#123c2c'>
    <title>{{ $title }} · {{ $appSettings->app_name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class='app-body'>
    <aside class='sidebar'>
        <a class='brand-lockup brand-lockup--light' href='{{ route('dashboard') }}' wire:navigate>
            <img class='brand-logo' src='{{ $appSettings->logoUrl() }}' alt='Logo {{ $appSettings->app_name }}'>
            <span class='app-brand-copy'><strong>{{ $appSettings->app_name }}</strong><small>PLAY FUN &amp; RISE UP</small></span>
        </a>

        <nav class='sidebar-nav' aria-label='Navigasi utama'>
            <p class='nav-label'>MENU UTAMA</p>
            @foreach ($navigation as $item)
                @php($navIcon = $item['icon'])
                <a
                    class='nav-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}'
                    href='{{ route($item['route']) }}'
                    wire:navigate
                    @if (request()->routeIs($item['active'])) aria-current='page' @endif
                >
                    <x-nav-icon :name='$navIcon' />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class='sidebar-account'>
            <div class='account-avatar' aria-hidden='true'>{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
            <div class='account-copy'>
                <strong>{{ auth()->user()->name }}</strong>
                <span>Administrator</span>
            </div>
            <form action='{{ route('logout') }}' method='POST'>
                @csrf
                <button class='logout-button' type='submit' aria-label='Keluar dari akun' title='Keluar'>
                    <svg viewBox='0 0 24 24' fill='none' aria-hidden='true'>
                        <path d='M10 5H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4m5-3 4-4-4-4m4 4H9' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/>
                    </svg>
                </button>
            </form>
        </div>
    </aside>

    <div class='mobile-shell'>
        <header class='mobile-header'>
            <a class='mobile-brand' href='{{ route('dashboard') }}' wire:navigate>
                <img class='brand-logo' src='{{ $appSettings->logoUrl() }}' alt='Logo {{ $appSettings->app_name }}'>
                <span class='app-brand-copy'><strong>{{ $appSettings->app_name }}</strong><small>PLAY FUN &amp; RISE UP</small></span>
            </a>
            <form action='{{ route('logout') }}' method='POST'>
                @csrf
                <button class='mobile-logout' type='submit'>Keluar</button>
            </form>
        </header>
        <nav class='mobile-nav' aria-label='Navigasi utama'>
            @foreach ($navigation as $item)
                @php($navIcon = $item['icon'])
                <a
                    class='mobile-nav-link {{ request()->routeIs($item['active']) ? 'is-active' : '' }}'
                    href='{{ route($item['route']) }}'
                    wire:navigate
                    @if (request()->routeIs($item['active'])) aria-current='page' @endif
                >
                    <x-nav-icon :name='$navIcon' />
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        </nav>
    </div>

    <main class='app-main'>
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
