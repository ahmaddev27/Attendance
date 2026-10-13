{{--
    Printable A4 per-employee tasks report.
    Expects $report (EmployeeTasksReportService::report()) and $generatedAt.
    Same font strategy as exports/_tabular-report: Amiri when the host has
    internet egress, DejaVu Sans (bundled, covers Arabic) otherwise.
--}}
@php
    $employee = $report['employee'];
    $totals = $report['totals'];
    $monthNames = collect($report['by_month'])->pluck('label', 'month');
    $period = $report['month'] ? ($monthNames[$report['month']] . ' ' . $report['year']) : ('عام ' . $report['year']);
    $roleLabels = ['assignee' => 'مسندة له', 'creator' => 'أنشأها'];
    $tiles = [
        'الإجمالي' => $totals['total'],
        'قيد الانتظار' => $totals['pending'],
        'قيد التنفيذ' => $totals['in_progress'],
        'مكتملة' => $totals['completed'],
        'متأخرة' => $totals['overdue'],
    ];
    $day = fn (?string $iso) => $iso ? substr($iso, 0, 10) : '—';
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير مهام {{ $employee['full_name'] }}</title>
    <style>
        {{-- No remote @font-face: dompdf's enable_remote=true was fetching
             fonts.gstatic.com on every request. The VPS can be slow or
             outright blocked for that host, which stalls the whole PDF
             pipeline and surfaces as "تعذّر تحميل PDF" to the admin.
             DejaVu Sans ships inside dompdf and covers Arabic cleanly. --}}

        @page { margin: 28px 24px 48px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1a1a1a; direction: rtl; margin: 0; }
        header.rpt-head { border-bottom: 3px solid #2678C4; padding-bottom: 8px; margin-bottom: 12px; }
        .brand { font-size: 20px; font-weight: bold; color: #2678C4; letter-spacing: 2px; direction: ltr; text-align: left; }
        h1 { margin: 4px 0 2px; font-size: 16px; color: #1a1a1a; }
        .meta { font-size: 10px; color: #4a4a4a; }
        table.tiles { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 12px; }
        table.tiles td { background: #f6faff; border: 1px solid #cfe2f5; text-align: center; padding: 8px 4px; }
        .tile-num { font-size: 18px; font-weight: bold; color: #2678C4; }
        .tile-label { font-size: 9px; color: #4a4a4a; }
        h2 { font-size: 12px; color: #2678C4; margin: 12px 0 6px; }
        table.rpt { width: 100%; border-collapse: collapse; direction: rtl; }
        table.rpt thead th { background: #2678C4; color: #fff; padding: 5px 3px; border: 1px solid #1f5f9d; text-align: center; }
        table.rpt tbody td { padding: 4px 3px; border: 1px solid #dcdcdc; text-align: center; }
        table.rpt tbody tr:nth-child(even) td { background: #f6faff; }
        td.title { text-align: right; }
        .overdue { color: #c0392b; font-weight: bold; }
    </style>
</head>
<body>
{{-- No <script type="text/php"> page footer: config/dompdf.php ships
     enable_php=false and dompdf errors out when the inline-PHP block is
     present. The report is almost always a single page anyway. --}}

<header class="rpt-head">
    <div class="brand">TAQAT</div>
    <h1>{{ $employee['full_name'] }}</h1>
    <div class="meta">
        الرقم الوظيفي: <span dir="ltr">{{ $employee['employee_number'] }}</span>
        &nbsp;|&nbsp; القسم: {{ $employee['department'] ?? '—' }}
        &nbsp;|&nbsp; الفترة: {{ $period }}
    </div>
</header>

<table class="tiles">
    <tr>
        @foreach ($tiles as $label => $value)
            <td>
                <div class="tile-num">{{ $value }}</div>
                <div class="tile-label">{{ $label }}</div>
            </td>
        @endforeach
    </tr>
</table>

<h2>التوزيع الشهري</h2>
<table class="rpt">
    <thead>
        <tr><th>الشهر</th><th>المهام</th><th>المكتملة</th><th>المتأخرة</th></tr>
    </thead>
    <tbody>
        @foreach ($report['by_month'] as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td>{{ $row['assigned'] }}</td>
                <td>{{ $row['completed'] }}</td>
                <td>{{ $row['overdue'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<h2>قائمة المهام ({{ count($report['tasks']) }})</h2>
<table class="rpt">
    <thead>
        <tr>
            <th>العنوان</th><th>الحالة</th><th>الأولوية</th><th>الدور</th>
            <th>تاريخ الإنشاء</th><th>الاستحقاق</th><th>الإكمال</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($report['tasks'] as $task)
            <tr>
                <td class="title">{{ $task['title'] }}</td>
                <td>{{ $task['status'] ?? '—' }}</td>
                <td>{{ $task['priority'] ?? '—' }}</td>
                <td>{{ $roleLabels[$task['assigned_role']] ?? '—' }}</td>
                <td>{{ $day($task['created_at']) }}</td>
                <td class="{{ $task['is_overdue'] ? 'overdue' : '' }}">{{ $task['due_date'] ?? '—' }}</td>
                <td>{{ $day($task['completed_at']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" style="padding:20px;color:#6a6a6a;">لا توجد مهام في هذه الفترة.</td></tr>
        @endforelse
    </tbody>
</table>

<div style="margin-top:10px;font-size:9px;color:#6a6a6a;direction:ltr;text-align:left;">
    Generated by TAQAT &middot; {{ $generatedAt }}
</div>
</body>
</html>
