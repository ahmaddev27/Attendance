'use client';

import { useParams } from 'next/navigation';

import { JobApplicationsView } from '@/components/recruitment/job-applications-view';

export default function JobCandidatesPage() {
  const params = useParams<{ id: string }>();
  return <JobApplicationsView jobId={Number(params.id)} />;
}
