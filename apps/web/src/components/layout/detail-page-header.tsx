import Link from 'next/link';
import { ArrowRight, ChevronLeft } from 'lucide-react';

type DetailPageHeaderProps = {
  listHref: string;
  listLabel: string;
  /** Last crumb — the record's own number/id, always LTR. */
  currentLabel: string;
};

/** Breadcrumb + "رجوع" link shared by every single-item detail page. */
export function DetailPageHeader({ listHref, listLabel, currentLabel }: DetailPageHeaderProps) {
  return (
    <div className="flex flex-wrap items-center justify-between gap-3">
      <nav aria-label="مسار التنقل" className="flex items-center gap-1.5 text-sm text-muted">
        <Link href={listHref} className="hover:text-brand">
          {listLabel}
        </Link>
        <ChevronLeft className="h-4 w-4" aria-hidden />
        <span className="num text-ink" dir="ltr">
          {currentLabel}
        </span>
      </nav>
      <Link href={listHref} className="inline-flex items-center gap-1.5 text-sm text-ink-2 hover:text-brand">
        <ArrowRight className="h-4 w-4" />
        رجوع
      </Link>
    </div>
  );
}
