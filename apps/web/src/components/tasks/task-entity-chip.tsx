'use client';

import Link from 'next/link';
import { Briefcase, Building2, FolderKanban, Target } from 'lucide-react';

import { cn } from '@/lib/utils';
import type { TaskEntity, TaskEntityType } from '@/lib/api/types';

const ENTITY_META: Record<TaskEntityType, { label: string; icon: React.ComponentType<{ className?: string }> }> = {
  lead: { label: 'عميل محتمل', icon: Target },
  client: { label: 'عميل', icon: Building2 },
  recruitment_case: { label: 'حملة', icon: FolderKanban },
  job_requirement: { label: 'وظيفة', icon: Briefcase },
};

type Props = {
  entity: TaskEntity;
  className?: string;
};

/**
 * "This task is about Job #2026-0045" — a deep link rendered next to a
 * task title. Stops click propagation because it usually sits inside a
 * card that is itself clickable (and draggable on the kanban).
 */
export function TaskEntityChip({ entity, className }: Props) {
  const meta = ENTITY_META[entity.type];
  const Icon = meta.icon;
  const text = entity.label ?? `${meta.label} #${entity.id}`;

  return (
    <Link
      href={entity.link}
      onClick={(e) => e.stopPropagation()}
      onPointerDown={(e) => e.stopPropagation()}
      title={`${meta.label}: ${text}`}
      className={cn(
        'inline-flex max-w-full items-center gap-1 rounded-md bg-brand-soft px-2 py-0.5 text-[11px] font-medium text-brand-ink hover:underline',
        className,
      )}
    >
      <Icon className="h-3 w-3 shrink-0" />
      <span className="truncate">{text}</span>
    </Link>
  );
}
