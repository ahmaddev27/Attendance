'use client';

import * as React from 'react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { GripVertical, Plus, RotateCcw, Save, Trash2, Undo2 } from 'lucide-react';
import { toast } from 'sonner';

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { SortableList } from '@/components/workflow/sortable-list';
import { OPTION_LISTS_QUERY_KEY } from '@/hooks/use-option-lists';
import {
  optionListsApi,
  type AdminOptionList,
  type OptionItem,
} from '@/lib/api/endpoints/option-lists';
import { cn } from '@/lib/utils';

export const ADMIN_OPTION_LISTS_QUERY_KEY = ['admin', 'option-lists'] as const;

type Row = OptionItem & { id: string };
type RowErrors = Record<number, { value?: string; label?: string }>;

type ValidationError = {
  response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } };
};

let rowSeq = 0;
const toRows = (items: OptionItem[]): Row[] => items.map((item) => ({ ...item, id: `row-${++rowSeq}` }));

const sameItems = (rows: Row[], items: OptionItem[]) =>
  rows.length === items.length &&
  rows.every((row, i) => row.value === items[i].value && row.label === items[i].label);

/**
 * Editor for one picker list: reorder by drag, edit code + label inline,
 * add/remove rows, save the whole list, or restore the code defaults.
 * Server-side validation errors are mapped back onto the offending row.
 */
export function OptionListEditor({ list }: { list: AdminOptionList }) {
  const queryClient = useQueryClient();
  const [rows, setRows] = React.useState<Row[]>(() => toRows(list.items));
  const [rowErrors, setRowErrors] = React.useState<RowErrors>({});
  const [formError, setFormError] = React.useState<string | null>(null);
  const [confirmReset, setConfirmReset] = React.useState(false);

  // Structural sharing keeps `list.items` referentially stable unless this
  // list actually changed on the server, so other editors keep their drafts.
  React.useEffect(() => {
    setRows(toRows(list.items));
    setRowErrors({});
    setFormError(null);
  }, [list.items]);

  const isCurrency = list.key === 'currencies';
  const dirty = !sameItems(rows, list.items);

  const applyServerList = (updated: AdminOptionList) => {
    queryClient.setQueryData<AdminOptionList[]>(ADMIN_OPTION_LISTS_QUERY_KEY, (prev) =>
      prev?.map((entry) => (entry.key === updated.key ? updated : entry)),
    );
    queryClient.invalidateQueries({ queryKey: OPTION_LISTS_QUERY_KEY });
  };

  const handleError = (err: unknown, fallback: string) => {
    const response = (err as ValidationError).response;
    if (response?.status !== 422 || !response.data?.errors) {
      toast.error(response?.data?.message ?? fallback);
      return;
    }

    const nextRowErrors: RowErrors = {};
    let nextFormError: string | null = null;
    for (const [field, messages] of Object.entries(response.data.errors)) {
      const match = field.match(/^items\.(\d+)\.(value|label)$/);
      if (match) {
        const index = Number(match[1]);
        nextRowErrors[index] = { ...nextRowErrors[index], [match[2]]: messages[0] };
      } else {
        nextFormError = messages[0];
      }
    }
    setRowErrors(nextRowErrors);
    setFormError(nextFormError);
    toast.error('راجع الحقول المظللة');
  };

  const saveMutation = useMutation({
    mutationFn: () =>
      optionListsApi.update(
        list.key,
        rows.map(({ value, label }) => ({ value, label })),
      ),
    onSuccess: (res) => {
      applyServerList(res.data.data);
      toast.success(`تم حفظ ${list.label}`);
    },
    onError: (err) => handleError(err, 'تعذر حفظ القائمة'),
  });

  const resetMutation = useMutation({
    mutationFn: () => optionListsApi.reset(list.key),
    onSuccess: (res) => {
      applyServerList(res.data.data);
      setConfirmReset(false);
      toast.success(`تمت استعادة القيم الافتراضية لـ ${list.label}`);
    },
    onError: (err) => {
      setConfirmReset(false);
      handleError(err, 'تعذرت الاستعادة');
    },
  });

  const updateRow = (id: string, patch: Partial<OptionItem>) => {
    setRows((prev) => prev.map((row) => (row.id === id ? { ...row, ...patch } : row)));
  };

  const removeRow = (id: string) => {
    setRows((prev) => prev.filter((row) => row.id !== id));
    setRowErrors({});
  };

  const addRow = () => {
    setRows((prev) => [...prev, ...toRows([{ value: '', label: '' }])]);
  };

  const busy = saveMutation.isPending || resetMutation.isPending;

  return (
    <section className="flex flex-col rounded-xl border border-hairline bg-surface p-5">
      <header className="mb-4">
        <div className="flex items-center justify-between gap-2">
          <h3 className="text-base font-semibold text-ink">{list.label}</h3>
          <span className="num text-xs text-muted" dir="ltr">
            {rows.length}
          </span>
        </div>
        <p className="mt-0.5 text-xs text-muted">{list.description}</p>
      </header>

      <div className="mb-2 grid grid-cols-[1.75rem_minmax(0,9rem)_minmax(0,1fr)_2rem] gap-2 px-1 text-[11px] font-semibold text-muted">
        <span />
        <span>الرمز</span>
        <span>الاسم الظاهر</span>
        <span />
      </div>

      <SortableList
        items={rows}
        getId={(row) => row.id}
        onReorder={(next) => {
          setRows(next);
          setRowErrors({});
        }}
        className="space-y-1.5"
        renderItem={(row, handle) => {
          const index = rows.indexOf(row);
          const errors = rowErrors[index];
          return (
            <div>
              <div className="grid grid-cols-[1.75rem_minmax(0,9rem)_minmax(0,1fr)_2rem] items-center gap-2">
                <button
                  type="button"
                  aria-label="اسحب لإعادة الترتيب"
                  className="grid h-9 cursor-grab touch-none place-items-center rounded-md text-muted hover:bg-surface-2 hover:text-ink active:cursor-grabbing"
                  {...handle.attributes}
                  {...handle.listeners}
                >
                  <GripVertical className="h-4 w-4" />
                </button>
                <Input
                  value={row.value}
                  onChange={(e) =>
                    updateRow(row.id, {
                      value: isCurrency ? e.target.value.toUpperCase().slice(0, 3) : e.target.value,
                    })
                  }
                  dir="ltr"
                  aria-label="الرمز"
                  aria-invalid={!!errors?.value}
                  className={cn('num text-start', errors?.value && 'border-danger focus-visible:ring-danger')}
                  placeholder={isCurrency ? 'USD' : 'code'}
                  maxLength={isCurrency ? 3 : 50}
                />
                <Input
                  value={row.label}
                  onChange={(e) => updateRow(row.id, { label: e.target.value })}
                  aria-label="الاسم الظاهر"
                  aria-invalid={!!errors?.label}
                  className={cn(errors?.label && 'border-danger focus-visible:ring-danger')}
                  maxLength={100}
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 text-danger hover:bg-danger-soft hover:text-danger"
                  aria-label="حذف العنصر"
                  onClick={() => removeRow(row.id)}
                  disabled={rows.length === 1}
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </Button>
              </div>
              {(errors?.value || errors?.label) && (
                <p className="mt-1 ps-9 text-[11px] text-danger">{errors.value ?? errors.label}</p>
              )}
            </div>
          );
        }}
      />

      <p className="mt-3 text-[11px] text-muted">{list.value_hint}</p>
      {formError && <p className="mt-1 text-xs text-danger">{formError}</p>}

      <div className="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-hairline pt-4">
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" className="gap-1.5" onClick={addRow} disabled={busy}>
            <Plus className="h-3.5 w-3.5" /> إضافة عنصر
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            className="gap-1.5 text-muted"
            onClick={() => setConfirmReset(true)}
            disabled={busy}
          >
            <RotateCcw className="h-3.5 w-3.5" /> القيم الافتراضية
          </Button>
        </div>
        <div className="flex flex-wrap gap-2">
          {dirty && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="gap-1.5"
              onClick={() => {
                setRows(toRows(list.items));
                setRowErrors({});
                setFormError(null);
              }}
              disabled={busy}
            >
              <Undo2 className="h-3.5 w-3.5" /> تجاهل
            </Button>
          )}
          <Button
            type="button"
            size="sm"
            className="gap-1.5 bg-brand text-white hover:bg-brand-hover"
            onClick={() => saveMutation.mutate()}
            disabled={!dirty || busy}
          >
            {saveMutation.isPending ? <Spinner className="text-white" /> : <Save className="h-3.5 w-3.5" />}
            حفظ
          </Button>
        </div>
      </div>

      <AlertDialog open={confirmReset} onOpenChange={setConfirmReset}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>استعادة القيم الافتراضية لـ {list.label}</AlertDialogTitle>
            <AlertDialogDescription>
              ستُحذف كل التعديلات على هذه القائمة. السجلات المحفوظة سابقاً تحتفظ بقيمها ولن تتغير.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>إلغاء</AlertDialogCancel>
            <AlertDialogAction onClick={() => resetMutation.mutate()} disabled={resetMutation.isPending}>
              استعادة
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </section>
  );
}
