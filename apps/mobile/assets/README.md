# Assets

Drop the following files into this folder before shipping a build. The
scaffold references them in `app.json` — Expo will warn (but continue)
when they're missing during `expo start`.

Required:

- `icon.png` — 1024×1024, app icon
- `adaptive-icon.png` — 1024×1024, Android adaptive foreground
- `splash.png` — 1284×2778 (or similar), splash artwork on the TAQAT blue
- `favicon.png` — 48×48 for the web build
- `notification-icon.png` — 96×96, monochrome white on transparent (Android)

Optional:

- `fonts/Tajawal-Regular.ttf`, `Tajawal-Bold.ttf`, … — brand font family
  referenced by `lib/theme.ts`. Register them in `app/_layout.tsx` via
  `expo-font` once bundled.
