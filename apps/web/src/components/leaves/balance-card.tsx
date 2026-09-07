import { Card } from '@/components/ui/card';
import { LeaveTypeBadge } from '@/components/leaves/leave-type-badge';
import type { LeaveBalance } from '@/lib/api/types';

type BalanceCardProps = {
  balance: LeaveBalance;
};

/**
 * Summary card for one balance-based leave type in a given year — the
 * remaining count is the headline number, with a breakdown + usage bar
 * underneath. Shared by the employee's own "my leaves" page and the admin
 * per-employee leaves sub-page so both render identical balance cards.
 */
export function BalanceCard({ balance }: BalanceCardProps) {
  const total = balance.entitlement + balance.carry_over_from_previous;
  const usedPct = total > 0 ? Math.min(100, (balance.used / total) * 100) : 0;
  const pendingPct = total > 0 ? Math.min(100 - usedPct, (balance.pending / total) * 100) : 0;

  return (
    <Card className="border-hairline bg-surface p-5">
      {balance.leave_type && <LeaveTypeBadge leaveType={balance.leave_type} className="text-sm font-medium" />}

      <p className="num mt-3 text-3xl font-bold text-ink">{balance.remaining}</p>
      <p className="text-xs text-muted">يوم متبقٍ</p>

      <div className="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-surface-2">
        <div className="flex h-full">
          <div className="h-full bg-brand" style={{ width: `${usedPct}%` }} />
          <div className="h-full bg-warn" style={{ width: `${pendingPct}%` }} />
        </div>
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
        <span>
          الرصيد: <span className="num font-medium text-ink-2">{total}</span>
        </span>
        <span className="flex items-center gap-1">
          <span className="h-1.5 w-1.5 rounded-full bg-brand" />
          <span className="num font-medium text-ink-2">{balance.used}</span> مستخدم
        </span>
        <span className="flex items-center gap-1">
          <span className="h-1.5 w-1.5 rounded-full bg-warn" />
          <span className="num font-medium text-ink-2">{balance.pending}</span> معلق
        </span>
      </div>
    </Card>
  );
}
