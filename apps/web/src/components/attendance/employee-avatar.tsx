import Image from 'next/image';

import { cn } from '@/lib/utils';
import type { EmployeeSummary } from '@/lib/api/types';

/** Employee avatar image, falling back to an initial-letter circle. */
export function EmployeeAvatar({
  employee,
  size = 32,
  className,
}: {
  employee: Pick<EmployeeSummary, 'full_name' | 'avatar_url'>;
  size?: number;
  className?: string;
}) {
  const initial = employee.full_name?.trim()?.charAt(0)?.toUpperCase() || '؟';

  if (employee.avatar_url) {
    return (
      <Image
        src={employee.avatar_url}
        alt={employee.full_name}
        width={size}
        height={size}
        unoptimized
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
