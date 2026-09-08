/**
 * Design tokens for TAQAT. Centralised so every component pulls from the
 * same palette / spacing scale — matches the web app's brand values.
 */

export const colors = {
  primary: '#2678C4',
  primaryDark: '#1F63A3',
  primaryLight: '#E8F1FA',
  accent: '#F5A623',
  accentDark: '#D48915',

  bg: '#F7F8FA',
  surface: '#FFFFFF',
  surfaceMuted: '#F1F3F6',
  border: '#E4E7EC',

  text: '#101828',
  textMuted: '#475467',
  textSubtle: '#98A2B3',
  textOnPrimary: '#FFFFFF',

  success: '#12B76A',
  danger: '#F04438',
  warning: '#F79009',
  info: '#0BA5EC',
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 24,
  '2xl': 32,
  '3xl': 48,
} as const;

export const radius = {
  sm: 6,
  md: 10,
  lg: 14,
  xl: 20,
  pill: 999,
} as const;

/**
 * Type scale. `fontFamily` intentionally defers to Tajawal when the font is
 * bundled; falls back to the system Arabic-capable UI font otherwise.
 */
export const typography = {
  fontFamily: 'Tajawal',
  h1: { fontSize: 28, lineHeight: 36, fontWeight: '700' as const },
  h2: { fontSize: 22, lineHeight: 30, fontWeight: '700' as const },
  h3: { fontSize: 18, lineHeight: 26, fontWeight: '600' as const },
  body: { fontSize: 15, lineHeight: 22, fontWeight: '400' as const },
  bodyStrong: { fontSize: 15, lineHeight: 22, fontWeight: '600' as const },
  small: { fontSize: 13, lineHeight: 18, fontWeight: '400' as const },
  caption: { fontSize: 12, lineHeight: 16, fontWeight: '500' as const },
} as const;

export const shadow = {
  card: {
    shadowColor: '#101828',
    shadowOpacity: 0.06,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 2,
  },
} as const;

export type Colors = typeof colors;
export type Spacing = typeof spacing;
export type Radius = typeof radius;
