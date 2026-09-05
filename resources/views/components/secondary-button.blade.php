<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-surface border border-hairline-strong text-ink-2 hover:bg-surface-2 rounded-lg text-sm font-semibold transition']) }}>
    {{ $slot }}
</button>
