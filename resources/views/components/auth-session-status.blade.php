@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-success bg-success-soft rounded-lg px-3 py-2 mb-4']) }}>
        {{ $status }}
    </div>
@endif
