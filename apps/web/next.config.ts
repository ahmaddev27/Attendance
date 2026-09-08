import type { NextConfig } from 'next';
import withSerwistInit from '@serwist/next';

/**
 * PWA wrapper — generates and registers a Serwist-based service worker
 * from `src/app/sw.ts`. Disabled in dev so hot-reload isn't fighting a
 * cached shell; enabled in every other env (build + prod).
 */
const withSerwist = withSerwistInit({
  swSrc: 'src/app/sw.ts',
  swDest: 'public/sw.js',
  disable: process.env.NODE_ENV === 'development',
  cacheOnNavigation: true,
  reloadOnOnline: true,
});

const nextConfig: NextConfig = {
  output: 'standalone',
  reactStrictMode: true,
};

export default withSerwist(nextConfig);
