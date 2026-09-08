import type { Metadata } from 'next';
import './globals.css';
import { Toaster } from '@/components/ui/sonner';
import { QueryProvider } from '@/components/providers/query-provider';

export const metadata: Metadata = {
  title: 'TAQAT - نظام إدارة الحضور',
  description: 'TAQAT Digital Workplace',
  // iOS Safari doesn't read the Web App Manifest for install metadata —
  // it needs these apple-* meta hints too so "Add to Home Screen" lands
  // as a full-screen PWA rather than a Safari-wrapped shortcut.
  appleWebApp: {
    capable: true,
    statusBarStyle: 'default',
    title: 'TAQAT',
  },
  formatDetection: { telephone: false },
};

export const viewport = {
  themeColor: '#2678C4',
  width: 'device-width',
  initialScale: 1,
  maximumScale: 5,
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="ar" dir="rtl">
      <body>
        <QueryProvider>
          {children}
          <Toaster position="top-left" richColors />
        </QueryProvider>
      </body>
    </html>
  );
}
