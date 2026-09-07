import type { Metadata } from 'next';
import './globals.css';
import { Toaster } from '@/components/ui/sonner';
import { QueryProvider } from '@/components/providers/query-provider';

export const metadata: Metadata = {
  title: 'TAQAT - نظام إدارة الحضور',
  description: 'TAQAT Digital Workplace',
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
