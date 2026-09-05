import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', 'system-ui', 'sans-serif'],
            },
            colors: {
                ground: 'var(--ground)',
                surface: 'var(--surface)',
                'surface-2': 'var(--surface-2)',
                ink: 'var(--ink)',
                'ink-2': 'var(--ink-2)',
                muted: 'var(--muted)',
                hairline: 'var(--hairline)',
                'hairline-strong': 'var(--hairline-strong)',
                brand: 'var(--brand)',
                'brand-hover': 'var(--brand-hover)',
                'brand-soft': 'var(--brand-soft)',
                'brand-ink': 'var(--brand-ink)',
                accent: 'var(--accent)',
                'accent-soft': 'var(--accent-soft)',
                'accent-ink': 'var(--accent-ink)',
                success: 'var(--success)',
                'success-soft': 'var(--success-soft)',
                warn: 'var(--warn)',
                'warn-soft': 'var(--warn-soft)',
                danger: 'var(--danger)',
                'danger-soft': 'var(--danger-soft)',
            },
            boxShadow: {
                soft: 'var(--shadow-sm)',
                DEFAULT: 'var(--shadow)',
                lg: 'var(--shadow-lg)',
            },
        },
    },

    plugins: [forms],
};
