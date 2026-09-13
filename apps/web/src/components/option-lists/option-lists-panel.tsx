'use client';

import { useQuery } from '@tanstack/react-query';

import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
  ADMIN_OPTION_LISTS_QUERY_KEY,
  OptionListEditor,
} from '@/components/option-lists/option-list-editor';
import { optionListsApi, type AdminOptionList } from '@/lib/api/endpoints/option-lists';

const GROUPS: { key: AdminOptionList['group']; title: string; description: string }[] = [
  { key: 'general', title: 'عام', description: 'قوائم تُستخدم على مستوى المنصة.' },
  { key: 'recruitment', title: 'التوظيف', description: 'قوائم نماذج العملاء المحتملين والعملاء والوظائف.' },
];

/**
 * Settings tab that lists every admin-editable picker list, grouped the
 * same way the API stores them.
 */
export function OptionListsPanel() {
  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ADMIN_OPTION_LISTS_QUERY_KEY,
    queryFn: async () => (await optionListsApi.adminList()).data.data,
  });

  if (isError) {
    return (
      <Card className="border-danger-soft bg-danger-soft/40 p-4 text-sm text-danger">
        تعذّر تحميل القوائم.{' '}
        <button type="button" onClick={() => refetch()} className="font-semibold underline">
          إعادة المحاولة
        </button>
      </Card>
    );
  }

  if (isLoading || !data) {
    return (
      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {[1, 2, 3, 4].map((i) => (
          <Skeleton key={i} className="h-72 w-full rounded-xl" />
        ))}
      </div>
    );
  }

  return (
    <div className="space-y-8">
      {GROUPS.map((group) => {
        const lists = data.filter((list) => list.group === group.key);
        if (lists.length === 0) return null;
        return (
          <section key={group.key} className="space-y-3">
            <div>
              <h2 className="text-sm font-semibold text-ink">{group.title}</h2>
              <p className="text-xs text-muted">{group.description}</p>
            </div>
            <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
              {lists.map((list) => (
                <OptionListEditor key={list.key} list={list} />
              ))}
            </div>
          </section>
        );
      })}
    </div>
  );
}
