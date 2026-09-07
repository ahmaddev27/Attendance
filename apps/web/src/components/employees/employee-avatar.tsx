import { cn } from '@/lib/utils';

type EmployeeAvatarProps = {
  employee: { full_name: string; avatar_url: string | null };
  size?: number;
  className?: string;
};

/**
 * Renders the employee's photo, or an initials fallback when there is none.
 * A plain <img> is used deliberately — avatar_url points at whatever media
 * host the API serves uploads from, which next/image would need to know
 * about ahead of time via `images.remotePatterns`.
 */
export function EmployeeAvatar({ employee, size = 32, className }: EmployeeAvatarProps) {
  if (employee.avatar_url) {
    // eslint-disable-next-line @next/next/no-img-element
    return (
      <img
        src={employee.avatar_url}
        alt={employee.full_name}
        width={size}
        height={size}
        className={cn('shrink-0 rounded-full object-cover', className)}
        style={{ width: size, height: size }}
      />
    );
  }

  const initials = employee.full_name.trim().charAt(0).toUpperCase() || '؟';

  return (
    <div
      className={cn(
        'grid shrink-0 place-items-center rounded-full bg-brand-soft text-xs font-semibold text-brand-ink',
        className
      )}
      style={{ width: size, height: size }}
    >
      {initials}
    </div>
  );
}
