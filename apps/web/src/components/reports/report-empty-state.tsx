import { FileSearch } from 'lucide-react';

/** Shared "no results yet / apply filters" placeholder for the report pages. */
export function ReportEmptyState({
  message = 'حدد الفلاتر ثم اضغط "تشغيل التقرير" لعرض النتائج',
}: {
  message?: string;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-hairline-strong bg-surface py-16 text-center">
      <div className="grid h-12 w-12 place-items-center rounded-full bg-surface-2 text-muted">
        <FileSearch className="h-6 w-6" />
      </div>
      <p className="max-w-sm text-sm text-muted">{message}</p>
    </div>
  );
}
