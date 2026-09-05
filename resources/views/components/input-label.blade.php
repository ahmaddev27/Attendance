@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-xs font-semibold text-ink-2 mb-1.5']) }}>
    {{ $value ?? $slot }}
</label>
