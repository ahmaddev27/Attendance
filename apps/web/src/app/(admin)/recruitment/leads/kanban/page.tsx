'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import {
  closestCorners,
  DndContext,
  DragOverlay,
  PointerSensor,
  useDroppable,
  useDraggable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { List, Plus } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { LeadFormDialog } from '@/app/(admin)/recruitment/leads/_components/lead-form-dialog';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { useOptionLists } from '@/hooks/use-option-lists';
import { leadsApi } from '@/lib/api/endpoints/recruitment';
import {
  LEAD_KANBAN_STATUSES,
  LEAD_STATUS_META,
} from '@/lib/constants/recruitment-options';
import { hasPermission, useAuthStore } from '@/lib/stores/auth-store';
import { cn } from '@/lib/utils';
import type { LeadKanbanData, LeadSource, LeadStatus, LeadSummary } from '@/lib/api/types';

const KANBAN_KEY = ['leads', 'kanban'];

/**
 * Kanban board for Leads — one column per non-terminal LeadStatus, drag
 * to change status. Optimistic move mirrors the tasks board pattern.
 */
export default function LeadsKanbanPage() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const user = useAuthStore((s) => s.user);
  const canManage = hasPermission(user, 'manage-leads');
  const { options } = useOptionLists();

  const [search, setSearch] = React.useState('');
  const [source, setSource] = React.useState<LeadSource | 'all'>('all');
  const [activeCard, setActiveCard] = React.useState<LeadSummary | null>(null);
  const [formOpen, setFormOpen] = React.useState(false);

  const debouncedSearch = useDebouncedValue(search);

  const filters = {
    search: debouncedSearch || undefined,
    source: source === 'all' ? undefined : source,
  };

  const { data, isLoading } = useQuery({
    queryKey: [...KANBAN_KEY, filters],
    queryFn: async () => (await leadsApi.kanban(filters)).data.data,
  });

  const findCard = React.useCallback(
    (id: number): { card: LeadSummary; column: LeadStatus } | null => {
      for (const [status, cards] of Object.entries(data ?? {}) as [LeadStatus, LeadSummary[]][]) {
        const card = cards?.find((c) => c.id === id);
        if (card) return { card, column: status };
      }
      return null;
    },
    [data],
  );

  const moveMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: LeadStatus }) =>
      leadsApi.update(id, { status }),
    onMutate: async ({ id, status }) => {
      await queryClient.cancelQueries({ queryKey: KANBAN_KEY });
      const previous = queryClient.getQueryData<LeadKanbanData>([...KANBAN_KEY, filters]);
      if (!previous) return { previous };

      const next: LeadKanbanData = {};
      let moved: LeadSummary | undefined;
      for (const [col, cards] of Object.entries(previous) as [LeadStatus, LeadSummary[]][]) {
        const remaining: LeadSummary[] = [];
        for (const card of cards ?? []) {
          if (card.id === id) {
            moved = { ...card, status };
          } else {
            remaining.push(card);
          }
        }
        next[col] = remaining;
      }
      if (moved) {
        next[status] = [moved, ...((next[status] ?? previous[status]) ?? [])];
      }
      queryClient.setQueryData([...KANBAN_KEY, filters], next);
      return { previous };
    },
    onError: (err, _v, ctx) => {
      if (ctx?.previous) queryClient.setQueryData([...KANBAN_KEY, filters], ctx.previous);
      const message = (err as { response?: { data?: { message?: string } } })?.response?.data?.message;
      toast.error(message || 'تعذر نقل السجل');
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['leads'] });
    },
  });

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

  const onDragStart = (e: DragStartEvent) => {
    const found = findCard(Number(e.active.id));
    setActiveCard(found?.card ?? null);
  };

  const onDragEnd = (e: DragEndEvent) => {
    setActiveCard(null);
    const { active, over } = e;
    if (!over || !canManage) return;
    const found = findCard(Number(active.id));
    const targetStatus = String(over.id) as LeadStatus;
    if (!found || found.column === targetStatus) return;
    if (!LEAD_KANBAN_STATUSES.includes(targetStatus)) return;
    moveMutation.mutate({ id: found.card.id, status: targetStatus });
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div>
          <p className="text-xs font-medium text-muted">التوظيف</p>
          <h1 className="mt-1 text-2xl font-bold text-ink">لوحة العملاء المحتملين</h1>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" variant="outline" className="gap-2" onClick={() => router.push('/recruitment/leads')}>
            <List className="h-4 w-4" />
            عرض قائمة
          </Button>
          {canManage && (
            <Button onClick={() => setFormOpen(true)} className="gap-2 bg-brand text-white hover:bg-brand-hover">
              <Plus className="h-4 w-4" />
              عميل محتمل جديد
            </Button>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 gap-3 rounded-xl border border-hairline bg-surface p-4 sm:grid-cols-3">
        <div className="sm:col-span-2">
          <Label className="text-xs font-semibold text-ink-2">بحث</Label>
          <Input
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            placeholder="ابحث باسم الشركة..."
            className="mt-1.5"
          />
        </div>
        <div>
          <Label className="text-xs font-semibold text-ink-2">المصدر</Label>
          <Select value={source} onValueChange={(v) => setSource(v as LeadSource | 'all')}>
            <SelectTrigger className="mt-1.5">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">كل المصادر</SelectItem>
              {options('lead_sources').map((opt) => (
                <SelectItem key={opt.value} value={opt.value}>
                  {opt.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {isLoading ? (
        <div className="flex gap-4 overflow-x-auto pb-2">
          {LEAD_KANBAN_STATUSES.map((s) => (
            <Skeleton key={s} className="h-[calc(100vh-320px)] w-80 shrink-0 rounded-xl" />
          ))}
        </div>
      ) : (
        <DndContext
          sensors={sensors}
          collisionDetection={closestCorners}
          onDragStart={onDragStart}
          onDragEnd={onDragEnd}
        >
          <div className="flex h-[calc(100vh-320px)] min-h-[520px] gap-4 overflow-x-auto overflow-y-hidden pb-3">
            {LEAD_KANBAN_STATUSES.map((status) => (
              <KanbanColumn
                key={status}
                status={status}
                cards={data?.[status] ?? []}
                onCardClick={(id) => router.push(`/recruitment/leads/${id}`)}
              />
            ))}
          </div>
          <DragOverlay>{activeCard && <KanbanCard card={activeCard} dragOverlay />}</DragOverlay>
        </DndContext>
      )}

      <LeadFormDialog open={formOpen} onOpenChange={setFormOpen} lead={null} />
    </div>
  );
}

function KanbanColumn({
  status,
  cards,
  onCardClick,
}: {
  status: LeadStatus;
  cards: LeadSummary[];
  onCardClick: (id: number) => void;
}) {
  const { setNodeRef, isOver } = useDroppable({ id: status });
  const meta = LEAD_STATUS_META[status];

  return (
    <div className="flex h-full w-80 shrink-0 flex-col rounded-xl border border-hairline bg-surface shadow-sm">
      <div className="flex items-center justify-between rounded-t-xl border-b border-hairline px-3 py-3">
        <div className="flex items-center gap-2">
          <span className={cn('h-2.5 w-2.5 rounded-full', meta.dotClassName)} aria-hidden="true" />
          <span className="text-sm font-semibold text-ink">{meta.label}</span>
        </div>
        <span className="num rounded-full bg-surface-2 px-2 py-0.5 text-xs font-semibold text-ink-2">
          {cards.length}
        </span>
      </div>
      <div
        ref={setNodeRef}
        className={cn(
          'flex flex-1 flex-col gap-2 overflow-y-auto p-2.5 transition-colors',
          isOver && 'bg-brand-soft/50',
        )}
      >
        {cards.length === 0 && (
          <div className="flex flex-1 items-center justify-center py-12 text-center text-xs text-muted">
            لا سجلات
          </div>
        )}
        {cards.map((card) => (
          <DraggableCard key={card.id} card={card} onClick={() => onCardClick(card.id)} />
        ))}
      </div>
    </div>
  );
}

function DraggableCard({ card, onClick }: { card: LeadSummary; onClick: () => void }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: card.id,
  });
  const style = transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined;

  return (
    <div ref={setNodeRef} style={style} className={cn(isDragging && 'opacity-40')}>
      <div className="group cursor-grab active:cursor-grabbing">
        <div {...attributes} {...listeners}>
          <KanbanCard card={card} onClick={onClick} />
        </div>
      </div>
    </div>
  );
}

function KanbanCard({
  card,
  onClick,
  dragOverlay,
}: {
  card: LeadSummary;
  onClick?: () => void;
  dragOverlay?: boolean;
}) {
  const { labelOf } = useOptionLists();

  return (
    <button
      type="button"
      onClick={onClick}
      className={cn(
        'flex w-full flex-col gap-1.5 rounded-lg border border-hairline bg-surface p-3 text-start shadow-sm transition-shadow hover:shadow',
        dragOverlay && 'rotate-1 shadow-lg',
      )}
    >
      <p className="truncate text-sm font-semibold text-ink" title={card.company_name}>
        {card.company_name}
      </p>
      <div className="flex items-center justify-between text-[11px] text-muted">
        <span className="num" dir="ltr">
          {card.lead_number}
        </span>
        <span>{labelOf('lead_sources', card.source)}</span>
      </div>
      {card.country && <p className="text-[11px] text-ink-2">{card.country}</p>}
      {card.expected_hiring_volume ? (
        <p className="num text-[11px] text-ink-2" dir="ltr">
          {card.expected_hiring_volume} openings
        </p>
      ) : null}
    </button>
  );
}
