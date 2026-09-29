@props(['name'])

<svg {{ $attributes->merge(['viewBox' => '0 0 24 24', 'fill' => 'none', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('dashboard')
            <path d='M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h6v6h-6v-6Z' stroke='currentColor' stroke-width='1.8' stroke-linejoin='round'/>
            @break
        @case('sessions')
            <path d='M7 3v3m10-3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z' stroke='currentColor' stroke-width='1.8' stroke-linecap='round'/>
            @break
        @case('members')
            <path d='M16 20v-1.5a4.5 4.5 0 0 0-4.5-4.5h-4A4.5 4.5 0 0 0 3 18.5V20m6.5-10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Zm7-1a3 3 0 0 1 0 5.8M21 20v-1a4 4 0 0 0-3-3.87' stroke='currentColor' stroke-width='1.8' stroke-linecap='round'/>
            @break
        @case('cash')
            <path d='M4 7.5h16v11H4v-11Zm0 3h16M16.5 15h.01M7 7.5V5.8A1.8 1.8 0 0 1 8.8 4H18' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/>
            @break
        @case('reports')
            <path d='M5 20V10m7 10V4m7 16v-7M3 20h18' stroke='currentColor' stroke-width='1.8' stroke-linecap='round'/>
            @break
        @case('settings')
            <path d='M12 15.2a3.2 3.2 0 1 0 0-6.4 3.2 3.2 0 0 0 0 6.4Zm7.4-3.2c0-.45-.05-.9-.13-1.32l1.62-1.26-1.8-3.12-1.9.76a7.7 7.7 0 0 0-2.29-1.33L14.6 3.7H11l-.3 2.03a7.7 7.7 0 0 0-2.29 1.33l-1.9-.76-1.8 3.12 1.62 1.26a7 7 0 0 0 0 2.64l-1.62 1.26 1.8 3.12 1.9-.76a7.7 7.7 0 0 0 2.29 1.33L11 20.3h3.6l.3-2.03a7.7 7.7 0 0 0 2.29-1.33l1.9.76 1.8-3.12-1.62-1.26c.08-.43.13-.87.13-1.32Z' stroke='currentColor' stroke-width='1.6' stroke-linejoin='round'/>
            @break
    @endswitch
</svg>
