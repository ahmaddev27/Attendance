'use client';

import {
  Bar,
  BarChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import type { EmployeeSummary } from '@/lib/api/endpoints/analytics';

/**
 * Recharts BarChart wrapper — dynamically imported so the recharts bundle
 * stays out of the admin shell and only loads on the analytics page.
 */
export type EmployeesByDepartmentChartProps = {
  data: EmployeeSummary['by_department'];
  color: string;
};

export default function EmployeesByDepartmentChart({
  data,
  color,
}: EmployeesByDepartmentChartProps) {
  return (
    <ResponsiveContainer width="100%" height={260}>
      <BarChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 40 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="rgb(228 232 239)" />
        <XAxis
          dataKey="name"
          tick={{ fontSize: 10 }}
          stroke="rgb(91 100 120)"
          angle={-30}
          textAnchor="end"
        />
        <YAxis tick={{ fontSize: 10 }} stroke="rgb(91 100 120)" allowDecimals={false} />
        <Tooltip
          contentStyle={{
            background: 'rgb(255 255 255)',
            border: '1px solid rgb(228 232 239)',
            borderRadius: 8,
            fontSize: 12,
          }}
        />
        <Bar dataKey="count" fill={color} radius={[4, 4, 0, 0]} name="عدد الموظفين" />
      </BarChart>
    </ResponsiveContainer>
  );
}
