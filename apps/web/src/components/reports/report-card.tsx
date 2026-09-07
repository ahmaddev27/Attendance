import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/components/ui/button';

type ReportCardProps = {
  icon: React.ComponentType<{ className?: string }>;
  title: string;
  description: string;
  href: string;
};

/** One card on the reports hub landing page — icon, title, description, CTA. */
export function ReportCard({ icon: Icon, title, description, href }: ReportCardProps) {
  return (
    <div className="flex flex-col rounded-xl border border-hairline bg-surface p-6">
      <div className="grid h-11 w-11 place-items-center rounded-lg bg-brand-soft text-brand-ink">
        <Icon className="h-5 w-5" />
      </div>
      <h3 className="mt-4 text-base font-semibold text-ink">{title}</h3>
      <p className="mt-1 flex-1 text-sm text-muted">{description}</p>
      <Button asChild className="mt-4 w-fit gap-2 bg-brand text-white hover:bg-brand-hover">
        <Link href={href}>
          فتح التقرير
          <ArrowLeft className="h-4 w-4" />
        </Link>
      </Button>
    </div>
  );
}
