'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';

import { Skeleton } from '@/components/ui/skeleton';
import { DetailPageHeader } from '@/components/layout/detail-page-header';
import { LeaveDetailView } from '@/components/leaves/leave-detail-view';
import { leaveRequestsApi } from '@/lib/api/endpoints/leaves';

/** Target of global-search results (`/leaves/{id}`). */
export default function LeaveDetailPage() {
  const id = Number(useParams<{ id: string }>().id);

  const { data: leave, isLoading } = useQuery({
    queryKey: ['leave-requests', id],
    queryFn: async () => (await leaveRequestsApi.get(id)).data.data,
    enabled: Number.isFinite(id),
  });

  return (
    <div className="space-y-6">
      <DetailPageHeader listHref="/leaves" listLabel="طلبات الإجازات" currentLabel={`#${id}`} />
      {isLoading && <Skeleton className="h-96 w-full" />}
      {!isLoading && !leave && <p className="text-sm text-muted">لم يتم العثور على طلب الإجازة</p>}
      {leave && <LeaveDetailView leave={leave} mode="admin" />}
    </div>
  );
}
