'use client';

import type * as React from 'react';

import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { AuditEventBadge } from '@/components/audit/audit-event-badge';
import { PropertyDiffViewer } from '@/components/audit/property-diff-viewer';
import { getModelBasename, getSubjectTypeLabel } from '@/lib/constants/audit-options';
import type { AuditLogEntry } from '@/lib/api/types';

function DetailRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-4 border-b border-hairline py-2.5 last:border-b-0">
      <span className="shrink-0 text-xs font-medium text-muted">{label}</span>
      <span className="text-sm text-ink">{children}</span>
    </div>
  );
}

/** Read-only detail view for a single audit log entry: metadata + the before/after diff. */
export function AuditLogDetailDialog({
  entry,
  open,
  onOpenChange,
}: {
  entry: AuditLogEntry | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>تفاصيل سجل النظام</DialogTitle>
        </DialogHeader>

        {entry && (
          <div>
            <div className="flex flex-wrap items-center gap-3 border-b border-hairline pb-4">
              <div className="min-w-0 flex-1">
                <p className="truncate font-medium text-ink">{entry.causer?.name ?? 'النظام'}</p>
                <p className="num text-xs text-muted" dir="ltr">
                  {new Date(entry.created_at).toLocaleString('ar-SA')}
                </p>
              </div>
              <AuditEventBadge event={entry.event} />
            </div>

            <div className="mt-2">
              <DetailRow label="الوصف">{entry.description || '—'}</DetailRow>
              <DetailRow label="النوع">{getSubjectTypeLabel(entry.subject_type)}</DetailRow>
              <DetailRow label="معرّف السجل">
                <span className="num">{entry.subject_id ?? '—'}</span>
              </DetailRow>
              <DetailRow label="مصدر الحدث">{getModelBasename(entry.causer_type)}</DetailRow>
              <DetailRow label="مجموعة السجل">{entry.log_name}</DetailRow>
            </div>

            <div className="mt-4">
              <h3 className="mb-2 text-xs font-semibold text-ink-2">التغييرات</h3>
              <PropertyDiffViewer properties={entry.properties} />
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
