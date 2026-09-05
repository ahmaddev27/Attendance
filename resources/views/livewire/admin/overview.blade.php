<div>
    <div class="pb-6 mb-6 border-b border-hairline">
        <div class="text-xs text-muted mb-1">الرئيسية</div>
        <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">نظرة عامة</h1>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-4">
        <div class="bg-surface border border-hairline rounded-xl p-5">
            <div class="text-[11.5px] text-muted font-medium mb-2">حضور اليوم</div>
            <div class="text-3xl font-bold text-ink num">
                {{ $presentToday }}<small class="text-[15px] text-muted font-medium mr-1">/ {{ $activeEmployees }}</small>
            </div>
        </div>
        <div class="bg-surface border border-hairline rounded-xl p-5">
            <div class="text-[11.5px] text-muted font-medium mb-2">غياب اليوم</div>
            <div class="text-3xl font-bold text-ink num">{{ $absentToday }}</div>
        </div>
        <div class="bg-surface border border-hairline rounded-xl p-5">
            <div class="text-[11.5px] text-muted font-medium mb-2">إجازة معتمدة</div>
            <div class="text-3xl font-bold text-ink num">{{ $approvedLeavesToday }}</div>
        </div>
        <div class="bg-surface border border-hairline rounded-xl p-5">
            <div class="text-[11.5px] text-muted font-medium mb-2">طلبات معلّقة</div>
            <div class="text-3xl font-bold num {{ $pendingLeaves > 0 ? 'text-warn' : 'text-ink' }}">
                {{ $pendingLeaves }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1.4fr_1fr] gap-4">
        <div class="bg-surface border border-hairline rounded-xl p-5">
            <div class="flex justify-between items-baseline mb-5">
                <h3 class="text-sm font-bold text-ink">حضور آخر ٧ أيام</h3>
                <span class="text-[11.5px] text-muted">أفراد فريدون</span>
            </div>
            @php $maxDay = max(1, $last7Days->max('count')); @endphp
            <div class="flex items-end gap-2 h-40 pt-3">
                @foreach ($last7Days as $i => $day)
                    <div class="flex-1 flex flex-col items-center gap-2">
                        <span class="text-[11px] text-muted font-semibold num">{{ $day['count'] }}</span>
                        <div
                            class="w-full rounded-t {{ $i === 6 ? 'bg-brand' : 'bg-brand-soft' }}"
                            style="height: {{ max(6, ($day['count'] / $maxDay) * 100) }}%"
                        ></div>
                        <span class="text-[10.5px] num {{ $i === 6 ? 'text-brand font-bold' : 'text-muted' }}">{{ $day['date'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-surface border border-hairline rounded-xl p-5">
            <div class="flex justify-between items-baseline mb-5">
                <h3 class="text-sm font-bold text-ink">آخر عمليات المسح</h3>
                <span class="text-[11.5px] text-muted">آخر ٥</span>
            </div>
            <div class="flex flex-col">
                @forelse ($recentScans as $scan)
                    <div class="flex items-center gap-3 py-2.5 border-b border-hairline last:border-b-0">
                        <div class="w-8 h-8 rounded-full bg-surface-2 text-ink-2 grid place-items-center text-xs font-bold flex-shrink-0">
                            {{ mb_substr($scan->employee->name, 0, 1) }}
                        </div>
                        <div class="flex-1 text-[13.5px] font-medium text-ink truncate">{{ $scan->employee->name }}</div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold {{ $scan->type->value === 'check_in' ? 'bg-success-soft text-success' : 'bg-surface-2 text-ink-2' }}">
                            {{ $scan->type->value === 'check_in' ? 'حضور' : 'انصراف' }}
                        </span>
                        <span class="text-[11.5px] text-muted num">{{ $scan->scanned_at->format('H:i') }}</span>
                    </div>
                @empty
                    <div class="text-sm text-muted text-center py-6">لا توجد عمليات مسح بعد</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
