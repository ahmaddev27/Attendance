import { ChevronLeft, ChevronRight } from 'lucide-react';

import { Button } from '@/components/ui/button';

/** Simple prev/next pagination bar used by the attendance log table. */
export function PaginationBar({
  currentPage,
  lastPage,
  total,
  onPageChange,
}: {
  currentPage: number;
  lastPage: number;
  total: number;
  onPageChange: (page: number) => void;
}) {
  if (lastPage <= 1) return null;

  return (
    <div className="flex items-center justify-between border-t border-hairline px-1 py-3">
      <p className="text-xs text-muted">
        صفحة <span className="num">{currentPage}</span> من <span className="num">{lastPage}</span> (
        <span className="num">{total}</span> سجل)
      </p>
      <div className="flex items-center gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={currentPage <= 1}
          onClick={() => onPageChange(currentPage - 1)}
        >
          <ChevronRight className="h-4 w-4" />
          السابق
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          disabled={currentPage >= lastPage}
          onClick={() => onPageChange(currentPage + 1)}
        >
          التالي
          <ChevronLeft className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
