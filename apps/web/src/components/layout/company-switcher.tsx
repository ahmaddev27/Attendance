'use client';

import { useQuery } from '@tanstack/react-query';
import { Building2 } from 'lucide-react';

import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { companiesApi } from '@/lib/api/endpoints/companies';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import { useCompanyScopeStore } from '@/lib/stores/company-scope-store';

/**
 * Soft Company Scoping — the admin header switcher.
 *
 * Shows the list of companies the admin can read (`view-clients` already
 * gates the `/api/companies` endpoint). A super-admin additionally sees
 * the "all companies" sentinel at the top so the global, unfiltered
 * view is one click away. Every list hook that cares about the current
 * scope reads `useCompanyScopeStore`; the store persists the id to
 * localStorage so a page reload keeps the selection.
 *
 * Hidden entirely when the user cannot read companies or only one is
 * configured — there is no choice to make at that point and the
 * dropdown would just occupy space.
 */
const ALL_COMPANIES_VALUE = 'all';

export function CompanySwitcher() {
  const user = useAuthStore((s) => s.user);
  const scopedCompanyId = useCompanyScopeStore((s) => s.scopedCompanyId);
  const setScopedCompanyId = useCompanyScopeStore((s) => s.setScopedCompanyId);

  const canSeeCompanies = hasPermission(user, 'view-clients');
  const isSuperAdmin = (user?.roles ?? []).includes('super-admin');

  const { data } = useQuery({
    queryKey: ['companies', 'switcher'],
    queryFn: async () => (await companiesApi.list({ per_page: 100 })).data.data,
    enabled: canSeeCompanies,
    staleTime: 60_000,
  });

  if (!canSeeCompanies) return null;
  const companies = data ?? [];
  if (companies.length === 0) return null;
  // One company configured and the admin is not a super-admin → nothing
  // to switch between. Super-admins keep the dropdown so they still see
  // the "all companies" affordance explicitly.
  if (companies.length === 1 && !isSuperAdmin) return null;

  const value = scopedCompanyId === null ? ALL_COMPANIES_VALUE : String(scopedCompanyId);

  const handleChange = (next: string) => {
    if (next === ALL_COMPANIES_VALUE) {
      setScopedCompanyId(null);
      return;
    }
    const parsed = Number(next);
    setScopedCompanyId(Number.isFinite(parsed) ? parsed : null);
  };

  return (
    <div className="flex items-center gap-2">
      <Building2 className="hidden h-4 w-4 text-ink-2 md:block" aria-hidden="true" />
      <Select value={value} onValueChange={handleChange}>
        <SelectTrigger
          className="h-9 min-w-[10rem] max-w-[14rem]"
          aria-label="تصفية حسب الشركة"
          title="تصفية حسب الشركة"
        >
          <SelectValue placeholder="كل الشركات" />
        </SelectTrigger>
        <SelectContent>
          {isSuperAdmin && (
            <SelectItem value={ALL_COMPANIES_VALUE}>كل الشركات</SelectItem>
          )}
          {companies.map((company) => (
            <SelectItem key={company.id} value={String(company.id)}>
              {company.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );
}
