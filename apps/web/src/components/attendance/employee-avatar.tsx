'use client';

import * as React from 'react';
import Image from 'next/image';

import { cn } from '@/lib/utils';
import type { EmployeeSummary } from '@/lib/api/types';

/** Employee avatar image, falling back to an initial-letter circle. */
export function EmployeeAvatar({
  employee,
  size = 32,
  className,
}: {
  // Nullable — attendance rows can survive an employee deletion (or arrive
  // from an eager-load that missed the join), and this component is
  // rendered inside a list where a per-row null shouldn't take down the
  // whole page. Accept null and render a neutral placeholder instead.
  employee: Pick<EmployeeSummary, 'full_name' | 'avatar_url'> | null | undefined;
  size?: number;
  className?: string;
}) {
  const [failed, setFailed] = React.useState(false);

  const avatarUrl = employee?.avatar_url ?? null;
  React.useEffect(() => {
    setFailed(false);
  }, [avatarUrl]);

  const initial = employee?.full_name?.trim()?.charAt(0)?.toUpperCase() || '؟';

  if (avatarUrl && !failed) {
    return (
      <Image
        src={avatarUrl}
        alt={employee?.full_name ?? ''}
        width={size}
        height={size}
        unoptimized
        onError={() => setFailed(true)}
        className={cn('shrink-0 rounded-full object-cover', className)}
        style={{ width: size, height: size }}
      />
    );
  }

  return (
    <div
      className={cn(
        'grid shrink-0 place-items-center rounded-full bg-brand-soft font-semibold text-brand-ink',
        className
      )}
      style={{ width: size, height: size, fontSize: size * 0.4 }}
    >
      {initial}
    </div>
  );
}
