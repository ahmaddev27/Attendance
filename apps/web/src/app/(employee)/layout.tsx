import { ProtectedRoute } from '@/components/auth/protected-route';
import { EmployeeHeader } from '@/components/layout/employee-header';
import { EmployeeSidebar } from '@/components/layout/employee-sidebar';

export default function EmployeeLayout({ children }: { children: React.ReactNode }) {
  return (
    <ProtectedRoute>
      <div className="min-h-screen bg-ground md:flex">
        <EmployeeSidebar />
        <div className="flex min-h-screen flex-1 flex-col md:ms-60">
          <EmployeeHeader />
          <main className="flex-1 p-6 md:p-10">
            <div className="mx-auto max-w-[1160px]">{children}</div>
          </main>
        </div>
      </div>
    </ProtectedRoute>
  );
}
