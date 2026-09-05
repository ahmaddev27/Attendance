<div>
    <div class="pb-6 mb-6 border-b border-hairline">
        <div class="text-xs text-muted mb-1">الحضور</div>
        <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">سجل الحضور والانصراف</h1>
    </div>

    <div class="flex flex-wrap gap-2.5 mb-4">
        <input type="date" wire:model.live="from" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">
        <input type="date" wire:model.live="to" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">

        <div wire:ignore class="min-w-[220px]">
            <select data-search data-placeholder="كل الموظفين" wire:model.live="employeeId" class="w-full">
                <option value="">كل الموظفين</option>
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->employee_number }})</option>
                @endforeach
            </select>
        </div>

        <div wire:ignore class="min-w-[170px]">
            <select data-search wire:model.live="type" class="w-full">
                <option value="all">حضور + انصراف</option>
                <option value="check_in">حضور</option>
                <option value="check_out">انصراف</option>
            </select>
        </div>

        <div wire:ignore class="min-w-[190px]">
            <select data-search wire:model.live="fraudStatus" class="w-full">
                <option value="all">كل حالات الفحص</option>
                <option value="passed">مقبول</option>
                <option value="skipped">بدون فحص</option>
                <option value="gps_failed">فشل فحص الموقع</option>
                <option value="ip_failed">فشل فحص IP</option>
            </select>
        </div>
    </div>

    <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
        <table class="w-full border-collapse">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:150px">الوقت</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider">الموظف</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:100px">النوع</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:140px">IP</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:140px">الفحص</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    <tr wire:key="attendance-{{ $record->id }}" class="border-t border-hairline hover:bg-surface-2 transition">
                        <td class="px-4 py-3 text-[13px]"><span class="num">{{ $record->scanned_at->format('Y-m-d H:i') }}</span></td>
                        <td class="px-4 py-3 text-[13px] font-medium text-ink">{{ $record->employee->name }}</td>
                        <td class="px-4 py-3 text-[13px]">
                            @if ($record->type->value === 'check_in')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-success-soft text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    حضور
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-2 text-ink-2">
                                    انصراف
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[13px]"><span class="num" dir="ltr">{{ $record->ip_address }}</span></td>
                        <td class="px-4 py-3 text-[13px]">
                            @switch($record->fraud_check_status->value)
                                @case('passed')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-success-soft text-success">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        مقبول
                                    </span>
                                    @break
                                @case('skipped')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-2 text-ink-2">
                                        بدون فحص
                                    </span>
                                    @break
                                @case('gps_failed')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-danger-soft text-danger">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        فشل فحص الموقع
                                    </span>
                                    @break
                                @case('ip_failed')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-danger-soft text-danger">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        فشل فحص IP
                                    </span>
                                    @break
                            @endswitch
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-muted">لا توجد سجلات حضور</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $records->links() }}</div>
</div>
