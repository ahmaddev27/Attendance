'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Building, ChevronDown, ChevronLeft, UsersRound } from 'lucide-react';

import { companiesApi } from '@/lib/api/endpoints/companies';
import type { CompanyTreeDepartment, CompanyTreeNode, CompanyTreeTeam } from '@/lib/api/types';
import { cn } from '@/lib/utils';

/**
 * Recursive tree view for Company > Departments > Teams.
 *
 * All nodes are collapsible; the top-level nodes default to expanded so an
 * operator sees the structure without extra clicks. Employee counts are
 * surfaced at the team level — the backend already pre-computes them with
 * `withCount('employees')` so no extra fetching happens here.
 */
export function OrgTree() {
  const { data, isLoading, isError } = useQuery({
    queryKey: ['companies', 'tree'],
    queryFn: async () => (await companiesApi.tree()).data.data,
  });

  if (isLoading) {
    return <p className="text-sm text-muted">جاري تحميل الهيكل التنظيمي...</p>;
  }
  if (isError) {
    return <p className="text-sm text-danger">تعذر تحميل الهيكل التنظيمي.</p>;
  }
  if (!data || data.length === 0) {
    return (
      <p className="rounded-lg border border-dashed border-hairline p-6 text-center text-sm text-muted">
        لا توجد شركات بعد. أضف شركة لرؤية الهيكل التنظيمي.
      </p>
    );
  }

  return (
    <div className="space-y-3">
      {data.map((company) => (
        <CompanyNode key={company.id} company={company} />
      ))}
    </div>
  );
}

function CompanyNode({ company }: { company: CompanyTreeNode }) {
  const [open, setOpen] = React.useState(true);
  const totalEmployees = React.useMemo(
    () =>
      company.departments.reduce(
        (sum, dept) => sum + dept.teams.reduce((s, team) => s + team.employee_count, 0),
        0,
      ),
    [company.departments],
  );

  return (
    <section className="rounded-xl border border-hairline bg-surface">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center gap-3 rounded-t-xl p-3 text-start hover:bg-surface-2"
      >
        <Chevron open={open} />
        {company.logo_url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={company.logo_url}
            alt={company.name}
            className="h-10 w-10 shrink-0 rounded-lg border border-hairline object-contain"
          />
        ) : (
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand">
            <Building className="h-5 w-5" />
          </div>
        )}
        <div className="min-w-0 flex-1">
          <p className="truncate font-semibold text-ink">{company.name}</p>
          <p className="text-xs text-muted">
            {company.departments.length} قسم · {totalEmployees} موظف
          </p>
        </div>
      </button>

      {open && (
        <div className="space-y-1 border-t border-hairline p-3">
          {company.departments.length === 0 ? (
            <p className="py-2 text-sm text-muted">لا توجد أقسام مرتبطة بهذه الشركة.</p>
          ) : (
            company.departments.map((dept) => <DepartmentNode key={dept.id} department={dept} />)
          )}
        </div>
      )}
    </section>
  );
}

function DepartmentNode({ department }: { department: CompanyTreeDepartment }) {
  const [open, setOpen] = React.useState(true);
  const totalEmployees = department.teams.reduce((s, team) => s + team.employee_count, 0);

  return (
    <div className="rounded-lg border border-hairline">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center gap-3 rounded-lg p-2.5 text-start hover:bg-surface-2"
      >
        <Chevron open={open} />
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-ink">{department.name}</p>
          <p className="text-[11px] text-muted">
            {department.teams.length} فريق · {totalEmployees} موظف
          </p>
        </div>
      </button>

      {open && department.teams.length > 0 && (
        <ul className="space-y-1 border-t border-hairline p-2">
          {department.teams.map((team) => (
            <TeamNode key={team.id} team={team} />
          ))}
        </ul>
      )}
    </div>
  );
}

function TeamNode({ team }: { team: CompanyTreeTeam }) {
  return (
    <li className="flex items-center gap-3 rounded-md px-2 py-1.5 text-sm text-ink-2 hover:bg-surface-2">
      <UsersRound className="h-4 w-4 shrink-0 text-muted" />
      <span className="min-w-0 flex-1 truncate">{team.name}</span>
      <span className="num rounded-md bg-surface-2 px-2 py-0.5 text-xs text-ink" dir="ltr">
        {team.employee_count}
      </span>
    </li>
  );
}

function Chevron({ open }: { open: boolean }) {
  // ChevronLeft points in the "collapse" direction for RTL layouts, so we
  // flip to ChevronDown when the node is expanded.
  const Icon = open ? ChevronDown : ChevronLeft;
  return <Icon className={cn('h-4 w-4 shrink-0 text-muted')} />;
}
