'use client';

import * as React from 'react';

import { cn } from '@/lib/utils';

type EmployeeAvatarProps = {
  // Nullable so upstream callers that pass optional data can rely on the
  // component to render a neutral placeholder instead of dereferencing null.
  employee: { full_name: string; avatar_url: string | null } | null | undefined;
  size?: number;
  className?: string;
};

/**
 * Renders the employee's photo, or an initials fallback when there is none,
 * when the URL is missing, or when the browser fails to load the image
 * (404/network). A plain <img> is used deliberately — avatar_url points at
 * whatever media host the API serves uploads from, which next/image would
 * need to know about ahead of time via `images.remotePatterns`.
 */
export function EmployeeAvatar({ employee, size = 32, className }: EmployeeAvatarProps) {
  const [failed, setFailed] = React.useState(false);

  // Reset the failure flag when the avatar URL changes — otherwise a fresh
  // upload would keep showing the initials fallback for the same component.
  const avatarUrl = employee?.avatar_url ?? null;
  React.useEffect(() => {
    setFailed(false);
  }, [avatarUrl]);

  if (avatarUrl && !failed) {
    // eslint-disable-next-line @next/next/no-img-element
    return (
      <img
        src={avatarUrl}
        alt={employee?.full_name ?? ''}
        width={size}
        height={size}
        onError={() => setFailed(true)}
        className={cn('shrink-0 rounded-full object-cover', className)}
        style={{ width: size, height: size }}
      />
    );
  }

  const initials = employee?.full_name?.trim()?.charAt(0)?.toUpperCase() || '؟';

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
