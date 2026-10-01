'use client';

import * as React from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Building, Globe, Pencil, Plus, Trash2 } from 'lucide-react';

import { FilterBar } from '@/components/data-table/filter-bar';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { companiesApi } from '@/lib/api/endpoints/companies';
import type { Company } from '@/lib/api/types';

import { CompanyFormDialog } from './_components/company-form-dialog';
import { DeleteCompanyDialog } from './_components/delete-company-dialog';
import { OrgTree } from './_components/org-tree';

const PER_PAGE = 50;

export default function CompaniesPage() {
  const [search, setSearch] = React.useState('');
  const debouncedSearch = useDebouncedValue(search);

  const [formOpen, setFormOpen] = React.useState(false);
  const [editingCompany, setEditingCompany] = React.useState<Company | null>(null);
  const [deletingCompany, setDeletingCompany] = React.useState<Company | null>(null);

  const { data, isLoading } = useQuery({
    queryKey: ['companies', 'list', { search: debouncedSearch }],
    queryFn: async () => {
      const { data } = await companiesApi.list({
        per_page: PER_PAGE,
        search: debouncedSearch || undefined,
      });
      return data;
    },
    placeholderData: keepPreviousData,
  });

  const openCreateDialog = () => {
    setEditingCompany(null);
    setFormOpen(true);
  };

  const openEditDialog = (company: Company) => {
    setEditingCompany(company);
    setFormOpen(true);
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">التنظيم</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">إدارة الشركات</h1>
        </div>
        <Button onClick={openCreateDialog} className="gap-2 bg-brand text-white hover:bg-brand-hover">
          <Plus className="h-4 w-4" />
          شركة جديدة
        </Button>
      </div>

      <Tabs defaultValue="list" className="space-y-4">
        <TabsList>
          <TabsTrigger value="list">الشركات</TabsTrigger>
          <TabsTrigger value="tree">عرض الهيكل التنظيمي</TabsTrigger>
        </TabsList>

        <TabsContent value="list" className="space-y-4">
          <FilterBar searchValue={search} onSearchChange={setSearch} searchPlaceholder="بحث باسم الشركة..." />

          <CompaniesGrid
            companies={data?.data ?? []}
            isLoading={isLoading}
            onEdit={openEditDialog}
            onDelete={setDeletingCompany}
          />
        </TabsContent>

        <TabsContent value="tree">
          <OrgTree />
        </TabsContent>
      </Tabs>

      <CompanyFormDialog open={formOpen} onOpenChange={setFormOpen} company={editingCompany} />
      <DeleteCompanyDialog
        open={!!deletingCompany}
        onOpenChange={(open) => !open && setDeletingCompany(null)}
        company={deletingCompany}
      />
    </div>
  );
}

type CompaniesGridProps = {
  companies: Company[];
  isLoading: boolean;
  onEdit: (company: Company) => void;
  onDelete: (company: Company) => void;
};

function CompaniesGrid({ companies, isLoading, onEdit, onDelete }: CompaniesGridProps) {
  if (isLoading) {
    return <p className="text-sm text-muted">جاري تحميل الشركات...</p>;
  }
  if (companies.length === 0) {
    return (
      <p className="rounded-lg border border-dashed border-hairline p-8 text-center text-sm text-muted">
        لا توجد شركات مطابقة لبحثك. أضف شركة جديدة لتبدأ.
      </p>
    );
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {companies.map((company) => (
        <CompanyCard key={company.id} company={company} onEdit={onEdit} onDelete={onDelete} />
      ))}
    </div>
  );
}

function CompanyCard({
  company,
  onEdit,
  onDelete,
}: {
  company: Company;
  onEdit: (c: Company) => void;
  onDelete: (c: Company) => void;
}) {
  return (
    <article className="flex flex-col gap-4 rounded-xl border border-hairline bg-surface p-5 shadow-sm transition-shadow hover:shadow">
      <div className="flex items-center gap-4">
        {company.logo_url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={company.logo_url}
            alt={company.name}
            className="h-14 w-14 shrink-0 rounded-lg border border-hairline object-contain"
          />
        ) : (
          <div className="grid h-14 w-14 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand">
            <Building className="h-6 w-6" />
          </div>
        )}
        <div className="min-w-0 flex-1">
          <h2 className="truncate text-base font-semibold text-ink">{company.name}</h2>
          <p className="flex items-center gap-1 text-xs text-muted">
            <Globe className="h-3.5 w-3.5" />
            <span dir="ltr">{company.timezone}</span>
          </p>
        </div>
      </div>

      <div className="flex items-center gap-2 text-xs text-ink-2">
        <span className="rounded-md bg-surface-2 px-2 py-1">
          <span className="num" dir="ltr">
            {company.departments_count ?? 0}
          </span>{' '}
          قسم
        </span>
      </div>

      <div className="mt-auto flex items-center justify-end gap-2 border-t border-hairline pt-3">
        <Button variant="outline" size="sm" onClick={() => onEdit(company)} className="gap-1.5">
          <Pencil className="h-3.5 w-3.5" />
          تعديل
        </Button>
        <Button
          variant="outline"
          size="sm"
          onClick={() => onDelete(company)}
          className="gap-1.5 border-danger/40 text-danger hover:bg-danger-soft"
        >
          <Trash2 className="h-3.5 w-3.5" />
          حذف
        </Button>
      </div>
    </article>
  );
}
