'use client';

import * as React from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Search } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { applicationsApi, candidatesApi } from '@/lib/api/endpoints/candidates';
import { cn } from '@/lib/utils';
import type { Candidate } from '@/lib/api/types';

type Props = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  jobId: number;
  /**
   * Candidate ids already attached to this job — those rows are hidden
   * from the picker so an admin cannot attach the same person twice.
   */
  excludeCandidateIds?: number[];
};

/**
 * Attach-existing-candidate picker. Searches the Candidate bank
 * server-side, filters out candidates already attached to this job
 * client-side, and POSTs the chosen id to `/jobs/{job}/applications`.
 * Inline-create is left to the Candidate bank page — this dialog is
 * strictly for linking an existing record.
 */
export function AttachCandidateDialog({
  open,
  onOpenChange,
  jobId,
  excludeCandidateIds = [],
}: Props) {
  const qc = useQueryClient();
  const [search, setSearch] = React.useState('');
  const [selectedId, setSelectedId] = React.useState<number | null>(null);
  const [notes, setNotes] = React.useState('');
  const debouncedSearch = useDebouncedValue(search, 300);

  React.useEffect(() => {
    if (!open) {
      setSelectedId(null);
      setNotes('');
      setSearch('');
    }
  }, [open]);

  const { data, isFetching } = useQuery({
    queryKey: ['candidates', 'picker', debouncedSearch],
    queryFn: async () =>
      (await candidatesApi.list({ search: debouncedSearch || undefined, per_page: 25 })).data,
    enabled: open,
    staleTime: 30_000,
  });

  const excluded = new Set(excludeCandidateIds);
  const rows: Candidate[] = (data?.data ?? []).filter((c) => !excluded.has(c.id));

  const mutation = useMutation({
    mutationFn: () =>
      applicationsApi.attach(jobId, {
        candidate_id: selectedId!,
        source: 'manual',
        notes: notes.trim() || null,
      }),
    onSuccess: () => {
      toast.success('تم ربط المرشّح بالوظيفة');
      qc.invalidateQueries({ queryKey: ['job-applications', jobId] });
      qc.invalidateQueries({ queryKey: ['job-shortlist', jobId] });
      onOpenChange(false);
    },
    onError: (err: unknown) => {
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر الربط');
    },
  });

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>ربط مرشّح موجود</DialogTitle>
          <DialogDescription>
            ابحث في بنك المرشّحين عن الشخص المطلوب ثم اختره. يمكنك إنشاء مرشّح جديد من صفحة بنك المرشّحين.
          </DialogDescription>
        </DialogHeader>

        <div className="relative">
          <Search className="pointer-events-none absolute start-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted" />
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="ابحث بالاسم أو البريد أو الهاتف..."
            className="ps-9"
          />
        </div>

        <div className="max-h-72 overflow-y-auto rounded-lg border border-hairline">
          {isFetching && rows.length === 0 ? (
            <div className="flex items-center justify-center p-6 text-sm text-muted">
              <Spinner className="me-2 h-4 w-4" />
              جارٍ البحث...
            </div>
          ) : rows.length === 0 ? (
            <p className="p-6 text-center text-sm text-muted">لا مرشّحين مطابقين</p>
          ) : (
            <ul className="divide-y divide-hairline">
              {rows.map((c) => {
                const isSelected = selectedId === c.id;
                return (
                  <li key={c.id}>
                    <button
                      type="button"
                      onClick={() => setSelectedId(c.id)}
                      className={cn(
                        'flex w-full items-center justify-between gap-3 px-4 py-2.5 text-start hover:bg-surface-2',
                        isSelected && 'bg-brand-soft',
                      )}
                    >
                      <div className="min-w-0">
                        <p className="truncate font-medium text-ink">{c.full_name}</p>
                        <p className="num truncate text-xs text-muted" dir="ltr">
                          {c.email || c.phone || '—'}
                          {c.headline ? ` · ${c.headline}` : ''}
                        </p>
                      </div>
                      <span className="num shrink-0 text-xs text-muted" dir="ltr">
                        {c.candidate_number}
                      </span>
                    </button>
                  </li>
                );
              })}
            </ul>
          )}
        </div>

        <div className="space-y-1.5">
          <label className="text-xs text-ink-2">ملاحظات (اختياري)</label>
          <Input value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={4000} />
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            إلغاء
          </Button>
          <Button
            type="button"
            className="bg-brand text-white hover:bg-brand-hover"
            disabled={!selectedId || mutation.isPending}
            onClick={() => mutation.mutate()}
          >
            {mutation.isPending && <Spinner className="me-2 h-4 w-4" />}
            ربط
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
