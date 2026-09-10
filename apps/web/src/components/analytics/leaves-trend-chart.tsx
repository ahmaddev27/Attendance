'use client';

import {
  CartesianGrid,
  Line,
  LineChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

import type { LeavePatterns } from '@/lib/api/endpoints/analytics';

/**
 * Wrapper around Recharts' LineChart used from the analytics dashboard.
 * Extracted so `next/dynamic` can code-split the ~200KB recharts bundle
 * out of the admin shell — the chunk only loads when this page renders.
 */
export type LeavesTrendChartProps = {
  data: LeavePatterns['by_month'];
  colors: { brand: string; warn: string };
};

export default function LeavesTrendChart({ data, colors }: LeavesTrendChartProps) {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <LineChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="rgb(228 232 239)" />
        <XAxis dataKey="month" tick={{ fontSize: 10 }} stroke="rgb(91 100 120)" />
        <YAxis tick={{ fontSize: 10 }} stroke="rgb(91 100 120)" />
        <Tooltip
          contentStyle={{
            background: 'rgb(255 255 255)',
            border: '1px solid rgb(228 232 239)',
            borderRadius: 8,
            fontSize: 12,
          }}
        />
        <Line
          type="monotone"
          dataKey="days"
          stroke={colors.brand}
          strokeWidth={2}
          dot={{ r: 3, fill: colors.brand }}
          name="أيام الإجازة"
        />
        <Line
          type="monotone"
          dataKey="requests"
          stroke={colors.warn}
          strokeWidth={2}
          dot={{ r: 3, fill: colors.warn }}
          name="عدد الطلبات"
        />
      </LineChart>
    </ResponsiveContainer>
  );
}
