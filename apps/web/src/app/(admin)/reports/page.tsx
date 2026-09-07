import { Building2, CalendarCheck2, CalendarOff, UserSearch } from 'lucide-react';

import { ReportCard } from '@/components/reports/report-card';

const REPORTS = [
  {
    icon: CalendarCheck2,
    title: 'تقرير الحضور',
    description: 'سجل حضور وانصراف الموظفين خلال فترة محددة، مع إمكانية التصفية والتصدير.',
    href: '/reports/attendance',
  },
  {
    icon: CalendarOff,
    title: 'تقرير الإجازات',
    description: 'ملخص طلبات الإجازات المقدمة والموافق عليها حسب النوع والقسم والفترة.',
    href: '/reports/leaves',
  },
  {
    icon: UserSearch,
    title: 'تقرير موظف شهري',
    description: 'أداء موظف واحد خلال شهر محدد: نسبة الحضور، أيام الغياب، المهام، والإجازات.',
    href: '/reports/monthly-employee',
  },
  {
    icon: Building2,
    title: 'تقرير أداء الأقسام',
    description: 'مقارنة الأقسام من حيث عدد الموظفين ونسبة الحضور ومعدل إنجاز المهام.',
    href: '/reports/department-performance',
  },
];

export default function ReportsHubPage() {
  return (
    <div>
      <div className="mb-6">
        <p className="text-xs font-medium text-muted">التقارير</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">مركز التقارير</h1>
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {REPORTS.map((report) => (
          <ReportCard key={report.href} {...report} />
        ))}
      </div>
    </div>
  );
}
