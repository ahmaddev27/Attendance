'use client';

import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';

import { Skeleton } from '@/components/ui/skeleton';
import { DetailPageHeader } from '@/components/layout/detail-page-header';
import { RequestDetailView } from '@/components/requests/request-detail-view';
import { myRequestsApi } from '@/lib/api/endpoints/requests';

export default function MyRequestDetailPage() {
  const id = Number(useParams<{ id: string }>().id);

  const { data: detail, isLoading } = useQuery({
    queryKey: ['my-requests', id],
    queryFn: async () => (await myRequestsApi.get(id)).data.data,
    enabled: Number.isFinite(id),
  });

  return (
    <div className="space-y-6">
      <DetailPageHeader
        listHref="/my-requests"
        listLabel="طلباتي"
        currentLabel={detail ? `#${detail.request_number}` : `#${id}`}
      />
      {isLoading && <Skeleton className="h-96 w-full" />}
      {!isLoading && !detail && <p className="text-sm text-muted">لم يتم العثور على الطلب</p>}
      {detail && <RequestDetailView detail={detail} mode="employee" />}
    </div>
  );
}
