@props(['model'])

<input
    type='text'
    inputmode='numeric'
    autocomplete='off'
    wire:model='{{ $model }}'
    x-data
    x-init="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
    x-on:input.capture="$el.value = $el.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')"
    {{ $attributes }}
>
