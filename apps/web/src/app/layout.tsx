import type { Metadata } from 'next';
import './globals.css';
import { Toaster } from '@/components/ui/sonner';
import { QueryProvider } from '@/components/providers/query-provider';
import { PwaInstaller } from '@/components/pwa-installer';

export const metadata: Metadata = {
  title: 'TAQAT - نظام إدارة الحضور',
  description: 'TAQAT Digital Workplace',
  // manifest/theme-color/apple-touch-icon are added as literal tags below
  // instead of through this API, matching the M8 PWA spec exactly and
  // avoiding Next duplicating them (it would otherwise inject its own
  // <link rel="manifest">/<meta name="theme-color"> from this object too).
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ar" dir="rtl">
      <head>
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#2678C4" />
        <link rel="apple-touch-icon" href="/img/logo.png" />
      </head>
      <body>
        <QueryProvider>
          {children}
          <Toaster position="top-left" richColors />
        </QueryProvider>
        <PwaInstaller />
      </body>
    </html>
  );
}
