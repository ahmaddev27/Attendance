<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-danger hover:bg-danger text-white rounded-lg text-sm font-semibold shadow transition']) }}>
    {{ $slot }}
</button>
