'use client';

import { useQuery } from '@tanstack/react-query';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { requestTypesApi } from '@/lib/api/endpoints/request-types';
import { resolveLucideIcon } from '@/lib/dynamic-icon';
import type { RequestType } from '@/lib/api/types';

type RequestTypePickerDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSelect: (requestType: RequestType) => void;
};

/** "طلب جديد" first step — pick which request type to submit before the dynamic form opens. */
export function RequestTypePickerDialog({ open, onOpenChange, onSelect }: RequestTypePickerDialogProps) {
  const { data: requestTypes, isLoading } = useQuery({
    queryKey: ['request-types', 'picker'],
    queryFn: async () => (await requestTypesApi.list()).data.data,
    enabled: open,
    staleTime: 60_000,
  });

  const activeTypes = (requestTypes ?? [])
    .filter((requestType) => requestType.is_active)
    .sort((a, b) => a.sort_order - b.sort_order);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>طلب جديد</DialogTitle>
          <DialogDescription>اختر نوع الطلب الذي تريد تقديمه</DialogDescription>
        </DialogHeader>

        {isLoading && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {Array.from({ length: 4 }).map((_, i) => (
              <Skeleton key={i} className="h-24 rounded-xl" />
            ))}
          </div>
        )}

        {!isLoading && activeTypes.length === 0 && (
          <p className="py-10 text-center text-sm text-muted">لا توجد أنواع طلبات متاحة حالياً</p>
        )}

        {!isLoading && activeTypes.length > 0 && (
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {activeTypes.map((requestType) => {
              const Icon = resolveLucideIcon(requestType.icon);
              return (
                <button
                  key={requestType.id}
                  type="button"
                  onClick={() => onSelect(requestType)}
                  className="flex items-start gap-3 rounded-xl border border-hairline bg-surface p-4 text-start transition-colors hover:border-brand hover:bg-brand-soft"
                >
                  <div
                    className="grid h-10 w-10 shrink-0 place-items-center rounded-lg"
                    style={{ backgroundColor: `${requestType.color}22` }}
                  >
                    {Icon ? (
                      <Icon className="h-5 w-5" style={{ color: requestType.color }} />
                    ) : (
                      <span className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: requestType.color }} />
                    )}
                  </div>
                  <div className="min-w-0">
                    <p className="font-medium text-ink">{requestType.name}</p>
                    {requestType.description && (
                      <p className="mt-0.5 line-clamp-2 text-xs text-muted">{requestType.description}</p>
                    )}
                  </div>
                </button>
              );
            })}
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
