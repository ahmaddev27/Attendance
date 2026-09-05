@props(['class' => 'h-12 w-auto'])

{{-- object-contain guards against callers forcing a fixed width+height box
     (e.g. w-10 h-10), which would otherwise stretch this non-square logo. --}}
<img src="{{ asset('img/logo.png') }}" alt="TAQAT"
     {{ $attributes->merge(['class' => trim($class).' object-contain']) }}>
