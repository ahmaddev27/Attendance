'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';

import { Skeleton } from '@/components/ui/skeleton';
import { DetailPageHeader } from '@/components/layout/detail-page-header';
import { LeaveDetailView } from '@/components/leaves/leave-detail-view';
import { myLeavesApi } from '@/lib/api/endpoints/leaves';

export default function MyLeaveDetailPage() {
  const id = Number(useParams<{ id: string }>().id);

  // There is no GET /me/leaves/{id} (the admin show route needs
  // approve-leaves), so the record is picked from the self-service list.
  const { data: leave, isLoading } = useQuery({
    queryKey: ['my-leaves'],
    queryFn: async () => (await myLeavesApi.list()).data.data,
    select: (leaves) => leaves.find((item) => item.id === id),
    enabled: Number.isFinite(id),
  });

  return (
    <div className="space-y-6">
      <DetailPageHeader listHref="/my-leaves" listLabel="إجازاتي" currentLabel={`#${id}`} />
      {isLoading && <Skeleton className="h-96 w-full" />}
      {!isLoading && !leave && <p className="text-sm text-muted">لم يتم العثور على طلب الإجازة</p>}
      {leave && <LeaveDetailView leave={leave} mode="employee" />}
    </div>
  );
}
