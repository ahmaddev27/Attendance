'use client';

import * as React from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { Eye, Video } from 'lucide-react';

import { Checkbox } from '@/components/ui/checkbox';
import { DataTable, type DataTableColumn } from '@/components/data-table/data-table';
import { FilterBar } from '@/components/data-table/filter-bar';
import { FilterSelect } from '@/components/data-table/filter-select';
import { InterviewKindBadge, InterviewStatusBadge } from '@/components/recruitment/status-badges';
import {
  interviewsApi,
  INTERVIEW_KIND_LABEL,
  INTERVIEW_STATUS_LABEL,
} from '@/lib/api/endpoints/candidates';
import { formatDate, formatTime } from '@/lib/attendance-format';
import type { Interview, InterviewKind, InterviewStatus } from '@/lib/api/types';

const KIND_OPTIONS = Object.entries(INTERVIEW_KIND_LABEL).map(([value, label]) => ({ value, label }));
const STATUS_OPTIONS = Object.entries(INTERVIEW_STATUS_LABEL).map(([value, label]) => ({ value, label }));

/**
 * Interview calendar as two tables — upcoming (soonest first) and past
 * (latest first). Grouping happens client-side on one page of results,
 * which is plenty for the filtered working set.
 */
export default function InterviewsPage() {
  const router = useRouter();
  const [kind, setKind] = React.useState<InterviewKind | undefined>();
  const [status, setStatus] = React.useState<InterviewStatus | undefined>();
  const [myOnly, setMyOnly] = React.useState(false);

  const filters = { per_page: 100, kind, status, my_only: myOnly || undefined };
  const { data, isLoading } = useQuery({
    queryKey: ['interviews', 'list', filters],
    queryFn: async () => (await interviewsApi.list(filters)).data.data,
  });

  const { upcoming, past } = React.useMemo(() => {
    const now = Date.now();
    const rows = data ?? [];
    const time = (i: Interview) => new Date(i.scheduled_at).getTime();
    return {
      upcoming: rows.filter((i) => time(i) >= now).sort((a, b) => time(a) - time(b)),
      past: rows.filter((i) => time(i) < now).sort((a, b) => time(b) - time(a)),
    };
  }, [data]);

  const columns: DataTableColumn<Interview>[] = [
    {
      key: 'candidate',
      header: 'المرشّح',
      cell: (i) => (
        <Link href={`/recruitment/interviews/${i.id}`} className="font-medium text-ink hover:underline">
          {i.application?.candidate?.full_name ?? '—'}
        </Link>
      ),
    },
    { key: 'job', header: 'الوظيفة', cell: (i) => i.application?.job?.title ?? '—' },
    {
      key: 'when',
      header: 'الموعد',
      cell: (i) => (
        <span className="num" dir="ltr">
          {formatDate(i.scheduled_at)} {formatTime(i.scheduled_at)} · {i.duration_minutes}د
        </span>
      ),
    },
    { key: 'kind', header: 'النوع', cell: (i) => <InterviewKindBadge kind={i.kind} /> },
    { key: 'status', header: 'الحالة', cell: (i) => <InterviewStatusBadge status={i.status} /> },
    {
      key: 'meeting',
      header: 'الرابط',
      cell: (i) =>
        i.meeting_url ? (
          <a
            href={i.meeting_url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1 text-brand-ink hover:underline"
          >
            <Video className="h-3.5 w-3.5" /> انضمام
          </a>
        ) : (
          '—'
        ),
    },
  ];

  const actions = [
    { label: 'عرض', icon: Eye, onClick: (i: Interview) => router.push(`/recruitment/interviews/${i.id}`) },
  ];

  return (
    <div className="space-y-6">
      <div>
        <p className="text-xs font-medium text-muted">التوظيف</p>
        <h1 className="mt-1 text-2xl font-bold text-ink">المقابلات</h1>
      </div>

      <FilterBar>
        <FilterSelect
          value={kind}
          onChange={(v) => setKind(v as InterviewKind | undefined)}
          options={KIND_OPTIONS}
          placeholder="النوع"
          allLabel="كل الأنواع"
        />
        <FilterSelect
          value={status}
          onChange={(v) => setStatus(v as InterviewStatus | undefined)}
          options={STATUS_OPTIONS}
          placeholder="الحالة"
          allLabel="كل الحالات"
        />
        <label className="flex cursor-pointer items-center gap-2 whitespace-nowrap text-sm text-ink">
          <Checkbox checked={myOnly} onCheckedChange={(c) => setMyOnly(c === true)} />
          مقابلاتي فقط
        </label>
      </FilterBar>

      <section className="space-y-3">
        <h2 className="text-sm font-semibold text-ink">القادمة</h2>
        <DataTable
          columns={columns}
          data={upcoming}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          emptyMessage="لا مقابلات قادمة"
          actions={actions}
        />
      </section>

      <section className="space-y-3">
        <h2 className="text-sm font-semibold text-ink">السابقة</h2>
        <DataTable
          columns={columns}
          data={past}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          emptyMessage="لا مقابلات سابقة"
          actions={actions}
        />
      </section>
    </div>
  );
}
