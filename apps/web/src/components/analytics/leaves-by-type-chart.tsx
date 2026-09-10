'use client';

import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

import type { LeavePatterns } from '@/lib/api/endpoints/analytics';

/**
 * Recharts PieChart wrapper — dynamically imported by the analytics page so
 * the recharts runtime is only pulled in when a user actually opens the
 * dashboard.
 */
export type LeavesByTypeChartProps = {
  data: LeavePatterns['by_type'];
  colors: readonly string[];
};

export default function LeavesByTypeChart({ data, colors }: LeavesByTypeChartProps) {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <PieChart>
        <Pie
          data={data}
          dataKey="days"
          nameKey="name"
          cx="50%"
          cy="50%"
          outerRadius={70}
          label={(entry) => `${entry.name}`}
          labelLine={false}
          style={{ fontSize: 11 }}
        >
          {data.map((_, i) => (
            <Cell key={i} fill={colors[i % colors.length]} />
          ))}
        </Pie>
        <Tooltip
          contentStyle={{
            background: 'rgb(255 255 255)',
            border: '1px solid rgb(228 232 239)',
            borderRadius: 8,
            fontSize: 12,
          }}
        />
      </PieChart>
    </ResponsiveContainer>
  );
}
