@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full px-3 py-2.5 border border-hairline-strong bg-surface rounded-lg text-sm text-ink placeholder:text-muted focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none disabled:opacity-60 transition']) }}>
