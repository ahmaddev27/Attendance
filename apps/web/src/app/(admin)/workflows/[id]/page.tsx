'use client';

import * as React from 'react';
import Link from 'next/link';
import { useParams } from 'next/navigation';
import { useQuery } from '@tanstack/react-query';
import { ArrowRight } from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { WorkflowStepsEditor } from '@/components/workflow/workflow-steps-editor';
import { workflowsApi } from '@/lib/api/endpoints/workflows';

export default function WorkflowDetailPage() {
  const params = useParams<{ id: string }>();
  const workflowId = Number(params.id);

  const { data: workflow, isLoading } = useQuery({
    queryKey: ['workflows', workflowId],
    queryFn: async () => (await workflowsApi.get(workflowId)).data.data,
    enabled: Number.isFinite(workflowId),
  });

  return (
    <div className="space-y-6">
      <div>
        <Link href="/workflows" className="inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink">
          <ArrowRight className="h-4 w-4" />
          مسارات العمل
        </Link>
      </div>

      {isLoading && (
        <div className="space-y-4">
          <Skeleton className="h-10 w-64" />
          <Skeleton className="h-40 w-full rounded-xl" />
        </div>
      )}

      {!isLoading && workflow && (
        <>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-2xl font-bold text-ink">{workflow.name}</h1>
            <Badge
              className={
                workflow.is_active
                  ? 'border-transparent bg-success-soft text-success'
                  : 'border-transparent bg-surface-2 text-muted'
              }
            >
              {workflow.is_active ? 'نشط' : 'غير نشط'}
            </Badge>
          </div>
          {workflow.description && <p className="text-sm text-muted">{workflow.description}</p>}

          <WorkflowStepsEditor workflow={workflow} />
        </>
      )}

      {!isLoading && !workflow && <p className="text-sm text-muted">تعذر العثور على مسار العمل المطلوب</p>}
    </div>
  );
}
