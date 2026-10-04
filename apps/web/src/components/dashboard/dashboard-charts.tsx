'use client';

import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

// Recharts cannot resolve CSS vars in fills, so brand tokens are mirrored as hex
// (same values as the analytics page).
export const CHART_COLORS = {
  brand: '#2678c4',
  success: '#1e9e7f',
  warn: '#f5a623',
  danger: '#c74f35',
  neutral: '#5b6478',
  brandInk: '#0f4a7f',
};

export const PIE_COLORS = [
  CHART_COLORS.brand,
  CHART_COLORS.success,
  CHART_COLORS.warn,
  CHART_COLORS.danger,
  CHART_COLORS.brandInk,
  CHART_COLORS.neutral,
];

const TOOLTIP_STYLE = {
  background: 'rgb(255 255 255)',
  border: '1px solid rgb(228 232 239)',
  borderRadius: 8,
  fontSize: 12,
};

export type StageDatum = { name: string; count: number };

/** Horizontal bars; axis reversed so bars grow from the right edge in RTL. */
export function StageBarChart({ data }: { data: StageDatum[] }) {
  const height = Math.max(160, data.length * 36 + 20);
  return (
    <ResponsiveContainer width="100%" height={height}>
      <BarChart data={data} layout="vertical" margin={{ top: 0, right: 0, left: 8, bottom: 0 }}>
        <XAxis type="number" reversed hide allowDecimals={false} />
        <YAxis
          type="category"
          dataKey="name"
          orientation="right"
          width={110}
          tick={{ fontSize: 11 }}
          stroke="rgb(91 100 120)"
          tickLine={false}
          axisLine={false}
        />
        <Tooltip contentStyle={TOOLTIP_STYLE} cursor={{ fill: 'rgb(244 246 250)' }} />
        <Bar dataKey="count" name="وظائف" fill={CHART_COLORS.brand} radius={4} barSize={16} />
      </BarChart>
    </ResponsiveContainer>
  );
}

export type TrendDatum = { label: string; present: number; absent: number };

/** Oldest day first + reversed axis => "اليوم" lands on the right. */
export function AttendanceTrendChart({ data }: { data: TrendDatum[] }) {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <BarChart data={data} margin={{ top: 5, right: 5, left: 5, bottom: 5 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="rgb(228 232 239)" vertical={false} />
        <XAxis dataKey="label" reversed tick={{ fontSize: 10 }} stroke="rgb(91 100 120)" />
        <YAxis orientation="right" allowDecimals={false} tick={{ fontSize: 10 }} stroke="rgb(91 100 120)" />
        <Tooltip contentStyle={TOOLTIP_STYLE} />
        <Legend wrapperStyle={{ fontSize: 11 }} />
        <Bar dataKey="present" name="حاضر" fill={CHART_COLORS.success} radius={[4, 4, 0, 0]} />
        <Bar dataKey="absent" name="غائب" fill={CHART_COLORS.danger} radius={[4, 4, 0, 0]} />
      </BarChart>
    </ResponsiveContainer>
  );
}

export type TypeDatum = { name: string; days: number };

export function LeaveTypePie({ data }: { data: TypeDatum[] }) {
  return (
    <ResponsiveContainer width="100%" height={220}>
      <PieChart>
        <Pie data={data} dataKey="days" nameKey="name" innerRadius={44} outerRadius={78} paddingAngle={2}>
          {data.map((_, i) => (
            <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
          ))}
        </Pie>
        <Tooltip contentStyle={TOOLTIP_STYLE} />
        <Legend wrapperStyle={{ fontSize: 11 }} />
      </PieChart>
    </ResponsiveContainer>
  );
}
