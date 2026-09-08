import { ProtectedRoute } from '@/components/auth/protected-route';
import { AdminHeader } from '@/components/layout/admin-header';
import { AdminSidebar } from '@/components/layout/admin-sidebar';

export default function AdminLayout({ children }: { children: React.ReactNode }) {
  return (
    <ProtectedRoute>
      <div className="min-h-screen bg-ground md:flex">
        <AdminSidebar />
        <div className="flex min-h-screen flex-1 flex-col md:mr-60">
          <AdminHeader />
          <main className="flex-1 p-6 md:p-10">
            <div className="mx-auto max-w-[1360px]">{children}</div>
          </main>
        </div>
      </div>
    </ProtectedRoute>
  );
}
