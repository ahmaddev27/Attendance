'use client';

import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import type { EmployeeTasksReport } from '@/lib/api/endpoints/employee-tasks-report';

/**
 * Monthly bars (created vs completed vs overdue). Split into its own module
 * so next/dynamic keeps recharts out of the page's initial chunk.
 */
export default function EmployeeTasksChart({ data }: { data: EmployeeTasksReport['by_month'] }) {
  return (
    <ResponsiveContainer width="100%" height={260}>
      <BarChart data={data} margin={{ top: 5, right: 10, left: 0, bottom: 5 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="rgb(228 232 239)" />
        <XAxis dataKey="label" tick={{ fontSize: 10 }} stroke="rgb(91 100 120)" />
        <YAxis allowDecimals={false} tick={{ fontSize: 10 }} stroke="rgb(91 100 120)" />
        <Tooltip contentStyle={{ borderRadius: 8, fontSize: 12 }} />
        <Legend wrapperStyle={{ fontSize: 12 }} />
        <Bar dataKey="assigned" name="المهام" fill="#2678C4" radius={[4, 4, 0, 0]} />
        <Bar dataKey="completed" name="المكتملة" fill="#16a34a" radius={[4, 4, 0, 0]} />
        <Bar dataKey="overdue" name="المتأخرة" fill="#dc2626" radius={[4, 4, 0, 0]} />
      </BarChart>
    </ResponsiveContainer>
  );
}
