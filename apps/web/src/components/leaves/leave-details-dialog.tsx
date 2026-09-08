'use client';

import type * as React from 'react';

import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { EmployeeAvatar } from '@/components/employees/employee-avatar';
import { LeaveStatusBadge } from '@/components/leaves/leave-status-badge';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import { formatDate } from '@/lib/attendance-format';
import type { LeaveRequest } from '@/lib/api/types';

type LeaveDetailsDialogProps = {
  leaveRequest: LeaveRequest | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

function DetailRow({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex items-start justify-between gap-4 border-b border-hairline py-2.5 last:border-b-0">
      <span className="shrink-0 text-xs font-medium text-muted">{label}</span>
      <span className="text-sm text-ink">{children}</span>
    </div>
  );
}

/** Read-only "view" dialog — full details of a single leave request. */
export function LeaveDetailsDialog({ leaveRequest, open, onOpenChange }: LeaveDetailsDialogProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>تفاصيل طلب الإجازة</DialogTitle>
        </DialogHeader>

        {leaveRequest && (
          <div>
            <div className="flex items-center gap-3 border-b border-hairline pb-4">
              <EmployeeAvatar employee={leaveRequest.employee} size={40} />
              <div className="min-w-0">
                <p className="truncate font-medium text-ink">{leaveRequest.employee.full_name}</p>
                <p className="num text-xs text-muted" dir="ltr">
                  {leaveRequest.employee.employee_number}
                </p>
              </div>
              <LeaveStatusBadge status={leaveRequest.status} className="ms-auto" />
            </div>

            <div className="mt-2">
              <DetailRow label="نوع الإجازة">
                <LeaveTypeBadge leaveType={leaveRequest.leave_type} />
              </DetailRow>
              <DetailRow label="تاريخ البداية">
                <span className="num" dir="ltr">
                  {formatDate(leaveRequest.start_date)}
                </span>
              </DetailRow>
              <DetailRow label="تاريخ النهاية">
                <span className="num" dir="ltr">
                  {formatDate(leaveRequest.end_date)}
                </span>
              </DetailRow>
              <DetailRow label="عدد الأيام">
                <span className="num" dir="ltr">
                  {leaveRequest.days}
                </span>
              </DetailRow>
              <DetailRow label="السبب">{leaveRequest.reason || '—'}</DetailRow>
              {leaveRequest.attachment_url && (
                <DetailRow label="المرفق">
                  <a
                    href={leaveRequest.attachment_url}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-brand-ink underline underline-offset-2"
                  >
                    عرض المرفق
                  </a>
                </DetailRow>
              )}
              <DetailRow label="تاريخ الإنشاء">
                <span className="num" dir="ltr">
                  {formatDate(leaveRequest.created_at)}
                </span>
              </DetailRow>
              {leaveRequest.reviewer && (
                <DetailRow label="تمت المراجعة بواسطة">{leaveRequest.reviewer.name}</DetailRow>
              )}
              {leaveRequest.reviewed_at && (
                <DetailRow label="تاريخ المراجعة">
                  <span className="num" dir="ltr">
                    {formatDate(leaveRequest.reviewed_at)}
                  </span>
                </DetailRow>
              )}
              {leaveRequest.status === 'rejected' && leaveRequest.rejection_reason && (
                <DetailRow label="سبب الرفض">
                  <span className="text-danger">{leaveRequest.rejection_reason}</span>
                </DetailRow>
              )}
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
