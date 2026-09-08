import type { MetadataRoute } from 'next';

/**
 * Web App Manifest — Next.js serves this at /manifest.webmanifest and wires
 * the <link rel="manifest"> tag automatically. Enables "Add to Home Screen"
 * / installable-PWA behavior on Android/Chromium and iOS.
 *
 * Icons live in /public/img/ so they're static assets, not bundled:
 *   - icon.png     — high-res source (used at 192 & 512 via any-purpose)
 *   - icon-maskable.png — Android maskable safe area (10% padding)
 * If those two files aren't present yet, the manifest still installs but
 * the OS falls back to a generic letter icon.
 */
export default function manifest(): MetadataRoute.Manifest {
  return {
    name: 'TAQAT — منصة العمل الرقمية',
    short_name: 'TAQAT',
    description: 'نظام إدارة الحضور والإجازات والمهام لموظفي TAQAT',
    start_url: '/',
    scope: '/',
    display: 'standalone',
    orientation: 'portrait',
    background_color: '#f6f8fb',
    theme_color: '#2678C4',
    lang: 'ar',
    dir: 'rtl',
    categories: ['business', 'productivity'],
    icons: [
      {
        src: '/img/icon.png',
        sizes: '192x192',
        type: 'image/png',
        purpose: 'any',
      },
      {
        src: '/img/icon.png',
        sizes: '512x512',
        type: 'image/png',
        purpose: 'any',
      },
      {
        src: '/img/icon-maskable.png',
        sizes: '512x512',
        type: 'image/png',
        purpose: 'maskable',
      },
    ],
  };
}
