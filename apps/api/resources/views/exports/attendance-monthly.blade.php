{{--
    Monthly attendance report — RTL PDF layout.

    Data model: App\Modules\Reports\Exports\AttendanceMonthlyPdfView
    Fields:
      $view->rows          Collection<int, array>   row payloads
      $view->year          int
      $view->month         int
      $view->generatedAt   string   'YYYY-MM-DD HH:MM'
      $view->totals()      array<string,int>
      $view->monthLabel()  string   Arabic month name

    Arabic rendering:
      DomPDF's bundled DejaVu Sans covers Arabic reasonably well and needs
      no external download. The @font-face below fetches Amiri from Google
      Fonts as a polish for prettier Arabic typography when the API host
      has internet egress. Bundling the TTF under storage/fonts/ is a
      Phase 3 polish — see config/dompdf.php.
--}}
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>تقرير الحضور الشهري - {{ $view->monthLabel() }} {{ $view->year }}</title>
    <style>
        {{--
            Amiri (Google Fonts) as an aesthetic upgrade — dompdf downloads
            the .ttf on first use and caches a .ufm+.cpg pair under
            storage/fonts/. The download is skipped in the `testing` env so
            phpunit runs don't try to reach fonts.gstatic.com from CI
            (which sandboxes external egress) and never fail on a missing
            font-cache write. DejaVu Sans is the DomPDF bundled fallback
            so the report always renders, with or without Amiri.
        --}}
        @if (app()->environment() !== 'testing')
        @font-face {
            font-family: 'Amiri';
            font-style: normal;
            font-weight: 400;
            src: url('https://fonts.gstatic.com/s/amiri/v27/J7aRnpd8CGxBHqUpvrIw74NL.ttf') format('truetype');
        }
        @endif

        * { box-sizing: border-box; }

        body {
            font-family: 'Amiri', 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            direction: rtl;
            margin: 0;
        }

        .page {
            padding: 24px 20px;
        }

        header.rpt-head {
            border-bottom: 3px solid #2678C4;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }

        header.rpt-head .brand {
            display: inline-block;
            vertical-align: middle;
            width: 46px;
            height: 46px;
            background: #2678C4;
            color: #ffffff;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            line-height: 46px;
            border-radius: 6px;
            margin-left: 12px;
        }

        header.rpt-head .title-wrap {
            display: inline-block;
            vertical-align: middle;
        }

        header.rpt-head h1 {
            margin: 0;
            font-size: 18px;
            color: #2678C4;
        }

        header.rpt-head .subtitle {
            margin: 2px 0 0;
            font-size: 12px;
            color: #4a4a4a;
        }

        table.rpt {
            width: 100%;
            border-collapse: collapse;
            direction: rtl;
        }

        table.rpt thead th {
            background: #2678C4;
            color: #ffffff;
            font-size: 11px;
            font-weight: bold;
            padding: 8px 6px;
            border: 1px solid #1f5f9d;
            text-align: center;
        }

        table.rpt tbody td {
            padding: 6px;
            border: 1px solid #dcdcdc;
            font-size: 10.5px;
            vertical-align: middle;
        }

        table.rpt tbody tr:nth-child(even) td {
            background: #f6faff;
        }

        table.rpt .num {
            text-align: center;
            direction: ltr;
        }

        table.rpt tfoot td {
            font-weight: bold;
            background: #eef4fb;
            border-top: 2px solid #2678C4;
            padding: 8px 6px;
        }

        footer.rpt-foot {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #dcdcdc;
            font-size: 9.5px;
            color: #6a6a6a;
            text-align: left;
            direction: ltr;
        }
    </style>
</head>
<body>
<div class="page">

    <header class="rpt-head">
        <span class="brand">TQ</span>
        <div class="title-wrap">
            <h1>تقرير الحضور الشهري</h1>
            <p class="subtitle">
                {{ $view->monthLabel() }} {{ $view->year }}
                &mdash; عدد الموظفين: {{ $view->rows->count() }}
            </p>
        </div>
    </header>

    <table class="rpt">
        <thead>
            <tr>
                <th>رقم الموظف</th>
                <th>الاسم</th>
                <th>القسم</th>
                <th>أيام الحضور</th>
                <th>أيام التأخير</th>
                <th>أيام الغياب</th>
                <th>أيام الإجازة</th>
                <th>إجمالي الدقائق</th>
                <th>دقائق الوقت الإضافي</th>
                <th>دقائق التأخير</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($view->rows as $row)
                <tr>
                    <td class="num">{{ $row['employee_number'] }}</td>
                    <td>{{ $row['full_name'] }}</td>
                    <td>{{ $row['department'] ?? '—' }}</td>
                    <td class="num">{{ $row['present_days'] }}</td>
                    <td class="num">{{ $row['late_days'] }}</td>
                    <td class="num">{{ $row['absent_days'] }}</td>
                    <td class="num">{{ $row['leave_days'] }}</td>
                    <td class="num">{{ number_format((int) $row['total_minutes']) }}</td>
                    <td class="num">{{ number_format((int) $row['overtime_minutes']) }}</td>
                    <td class="num">{{ number_format((int) $row['late_minutes']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" style="text-align:center;padding:24px;color:#6a6a6a;">
                        لا توجد بيانات لهذا الشهر.
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if ($view->rows->isNotEmpty())
            @php $t = $view->totals(); @endphp
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align:right;">الإجمالي</td>
                    <td class="num">{{ $t['present_days'] }}</td>
                    <td class="num">{{ $t['late_days'] }}</td>
                    <td class="num">{{ $t['absent_days'] }}</td>
                    <td class="num">{{ $t['leave_days'] }}</td>
                    <td class="num">{{ number_format($t['total_minutes']) }}</td>
                    <td class="num">{{ number_format($t['overtime_minutes']) }}</td>
                    <td class="num">{{ number_format($t['late_minutes']) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    <footer class="rpt-foot">
        Generated by TAQAT &middot; {{ $view->generatedAt }}
    </footer>
</div>
</body>
</html>
