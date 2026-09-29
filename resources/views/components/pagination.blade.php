@php
    $scrollTarget = $scrollTo ?? 'body';
    $scrollIntoView = $scrollTarget === false
        ? ''
        : "(\$el.closest('{$scrollTarget}') || document.querySelector('{$scrollTarget}')).scrollIntoView({ behavior: 'smooth' })";
@endphp

@if ($paginator->hasPages())
    <nav class='fuma-pagination' role='navigation' aria-label='Navigasi halaman'>
        <p class='pagination-summary'>
            Menampilkan <strong>{{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }}</strong>
            dari <strong>{{ $paginator->total() }}</strong> data
        </p>

        <div class='pagination-controls'>
            @if ($paginator->onFirstPage())
                <span class='pagination-button pagination-button--wide is-disabled' aria-disabled='true'>Sebelumnya</span>
            @else
                <button class='pagination-button pagination-button--wide' type='button' wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoView }}" wire:loading.attr='disabled'>Sebelumnya</button>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class='pagination-button pagination-gap' aria-hidden='true'>{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page === $paginator->currentPage())
                            <span class='pagination-button pagination-page-number is-current' aria-current='page'>{{ $page }}</span>
                        @else
                            <button class='pagination-button pagination-page-number' type='button' wire:key='pagination-{{ $paginator->getPageName() }}-{{ $page }}' wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoView }}" aria-label='Buka halaman {{ $page }}'>{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <button class='pagination-button pagination-button--wide' type='button' wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoView }}" wire:loading.attr='disabled'>Berikutnya</button>
            @else
                <span class='pagination-button pagination-button--wide is-disabled' aria-disabled='true'>Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
