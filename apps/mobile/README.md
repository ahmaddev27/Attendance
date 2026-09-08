# TAQAT Mobile

Employee HR / attendance app for the TAQAT platform.

Built with Expo (SDK 51+, managed workflow), expo-router, TanStack Query,
Zustand, and Axios. Talks to the Laravel API at
`https://attendees.taqatgaza.com/api` using Sanctum bearer tokens.

## Getting started

```bash
cd apps/mobile
npm install
npx expo start
```

Then scan the QR with **Expo Go** on an Arabic-set device (iOS or Android),
or press `a` / `i` to launch a simulator.

## Environment

Copy `.env.example` to `.env` and adjust:

```
EXPO_PUBLIC_API_URL=https://attendees.taqatgaza.com/api
```

`EXPO_PUBLIC_*` vars are inlined into the JS bundle at build time and are
safe to commit into `.env.example` only (not `.env`).

## Architecture

```
apps/mobile/
├── app/                    # expo-router file-based routes
│   ├── _layout.tsx         # root: QueryClient + RTL + auth gate
│   ├── (auth)/
│   │   ├── _layout.tsx
│   │   └── login.tsx       # employee_number + password
│   ├── (tabs)/
│   │   ├── _layout.tsx     # bottom tab bar
│   │   ├── index.tsx       # Home
│   │   ├── tasks.tsx       # My tasks
│   │   ├── leaves.tsx      # My leaves
│   │   └── profile.tsx     # Profile + logout
│   └── scan.tsx            # QR scanner (modal)
├── components/             # Button, Card, ...
├── hooks/                  # useAuth, ...
├── lib/                    # api client, auth store, theme
└── assets/                 # icons, splash, fonts, locales
```

Layered per the monorepo's clean-architecture convention:

- **UI** (`app/`, `components/`) — presentation only.
- **Hooks** (`hooks/`) — orchestrate UI + services.
- **Lib** (`lib/`) — HTTP client, state stores, domain constants. Zero UI.

## Notes / TODOs

- **Fonts**: `Tajawal` isn't bundled — drop the `.ttf` files under
  `assets/fonts/` and register them in `app/_layout.tsx` when ready.
- **Splash / icons**: replace the placeholders under `assets/` (icon.png,
  splash.png, adaptive-icon.png, favicon.png, notification-icon.png).
- **Firebase / FCM**: push notifications need a native project. Run
  `npx expo install expo-notifications` (already listed) and add the
  Firebase config per Expo's guide before shipping to Play Store.
- **EAS**: `app.json > extra.eas.projectId` is a placeholder — run
  `eas init` once to fill it in.
