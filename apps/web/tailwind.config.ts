import type { Config } from 'tailwindcss';

/**
 * Tailwind config for TAQAT web.
 *
 * Two token systems live side by side here:
 *  - shadcn/ui's semantic tokens (background, foreground, card, popover,
 *    primary, secondary, muted, accent, destructive, border, input, ring,
 *    chart) — required as-is by the generated components in
 *    src/components/ui/*, so Button/Select/Dialog/etc. keep their
 *    accessible, tested light/dark styling out of the box.
 *  - TAQAT's own brand tokens (ground, surface, ink, brand, success, warn,
 *    danger, …) migrated from _v1_artifacts/tokens/design-tokens.css, used
 *    directly by app pages (bg-brand, text-ink, border-hairline, …).
 *
 * `muted` is the one place both systems would collide: shadcn's default
 * `muted` is a near-white background tone, unsuitable for direct use as
 * text (e.g. `text-muted` on the login page). It's remapped below so
 * `muted.DEFAULT` matches TAQAT's own --muted ink tone (the actual color
 * name for it in design-tokens.css), while `muted.foreground` stays a
 * high-contrast ink for the rare case something sits on a `bg-muted`
 * surface. `accent` is deliberately left on shadcn's neutral hover/focus
 * gray — TAQAT's brand accent-orange is already exposed via `warn`
 * (identical value in design-tokens.css) so it doesn't hijack every
 * outline/ghost button hover and select-item focus state app-wide.
 */
const config: Config = {
  darkMode: 'class',
  content: ['./src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Tajawal', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        DEFAULT: 'var(--radius)',
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
      },
      colors: {
        // shadcn/ui semantic tokens
        background: 'hsl(var(--background))',
        foreground: 'hsl(var(--foreground))',
        card: {
          DEFAULT: 'hsl(var(--card))',
          foreground: 'hsl(var(--card-foreground))',
        },
        popover: {
          DEFAULT: 'hsl(var(--popover))',
          foreground: 'hsl(var(--popover-foreground))',
        },
        primary: {
          DEFAULT: 'hsl(var(--primary))',
          foreground: 'hsl(var(--primary-foreground))',
        },
        secondary: {
          DEFAULT: 'hsl(var(--secondary))',
          foreground: 'hsl(var(--secondary-foreground))',
        },
        muted: {
          DEFAULT: 'rgb(var(--muted) / <alpha-value>)',
          foreground: 'rgb(var(--ink) / <alpha-value>)',
        },
        accent: {
          DEFAULT: 'hsl(var(--accent))',
          foreground: 'hsl(var(--accent-foreground))',
        },
        destructive: {
          DEFAULT: 'hsl(var(--destructive))',
          foreground: 'hsl(var(--destructive-foreground))',
        },
        border: 'hsl(var(--border))',
        input: 'hsl(var(--input))',
        ring: 'hsl(var(--ring))',
        chart: {
          '1': 'hsl(var(--chart-1))',
          '2': 'hsl(var(--chart-2))',
          '3': 'hsl(var(--chart-3))',
          '4': 'hsl(var(--chart-4))',
          '5': 'hsl(var(--chart-5))',
        },

        // TAQAT brand tokens
        ground: 'rgb(var(--ground) / <alpha-value>)',
        surface: 'rgb(var(--surface) / <alpha-value>)',
        'surface-2': 'rgb(var(--surface-2) / <alpha-value>)',
        ink: 'rgb(var(--ink) / <alpha-value>)',
        'ink-2': 'rgb(var(--ink-2) / <alpha-value>)',
        hairline: 'rgb(var(--hairline) / <alpha-value>)',
        'hairline-strong': 'rgb(var(--hairline-strong) / <alpha-value>)',
        brand: {
          DEFAULT: 'rgb(var(--brand) / <alpha-value>)',
          hover: 'rgb(var(--brand-hover) / <alpha-value>)',
          soft: 'rgb(var(--brand-soft) / <alpha-value>)',
          ink: 'rgb(var(--brand-ink) / <alpha-value>)',
        },
        success: {
          DEFAULT: 'rgb(var(--success) / <alpha-value>)',
          soft: 'rgb(var(--success-soft) / <alpha-value>)',
        },
        warn: {
          DEFAULT: 'rgb(var(--warn) / <alpha-value>)',
          soft: 'rgb(var(--warn-soft) / <alpha-value>)',
          ink: 'rgb(var(--accent-ink) / <alpha-value>)',
        },
        danger: {
          DEFAULT: 'rgb(var(--danger) / <alpha-value>)',
          soft: 'rgb(var(--danger-soft) / <alpha-value>)',
        },
      },
    },
  },
  plugins: [require('tailwindcss-animate')],
};
export default config;
