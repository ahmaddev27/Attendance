@props(['class' => 'h-10 w-auto'])

<img src="{{ asset('img/logo.png') }}" alt="TAQAT" {{ $attributes->merge(['class' => $class]) }}>
