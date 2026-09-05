<button {{ $attributes->merge(['type' => 'submit', 'class' => 'w-full inline-flex items-center justify-center px-4 py-2.5 bg-brand hover:bg-brand-hover active:bg-brand-hover text-white rounded-lg text-sm font-semibold shadow transition']) }}>
    {{ $slot }}
</button>
