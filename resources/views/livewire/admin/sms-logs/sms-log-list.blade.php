<div>
    <div class="flex justify-between items-end pb-6 mb-6 border-b border-hairline">
        <div>
            <div class="text-xs text-muted mb-1">سجل SMS</div>
            <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">الرسائل المرسلة</h1>
        </div>
    </div>

    <div class="flex flex-wrap gap-2.5 mb-4">
        <select wire:model.live="status" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">
            <option value="all">كل الحالات</option>
            <option value="sent">مرسلة</option>
            <option value="failed">فشلت</option>
        </select>

        <input
            type="text"
            wire:model.live.debounce.300ms="phone"
            placeholder="بحث برقم الجوال…"
            class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface min-w-[200px] placeholder:text-muted"
            dir="ltr"
        >

        <input type="date" wire:model.live="from" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">
        <input type="date" wire:model.live="to" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">
    </div>

    <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
        <table class="w-full border-collapse">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:150px">الوقت</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:150px">الجوال</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider">الرسالة</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:100px">الحالة</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:90px">الخطأ</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr wire:key="sms-log-{{ $log->id }}" class="border-t border-hairline hover:bg-surface-2 transition">
                        <td class="px-4 py-3 text-[13px]"><span class="num">{{ $log->sent_at->format('Y-m-d H:i') }}</span></td>
                        <td class="px-4 py-3 text-[13px]"><span class="num" dir="ltr">{{ $log->phone }}</span></td>
                        <td class="px-4 py-3 text-[12.5px] text-ink-2">{{ $log->message }}</td>
                        <td class="px-4 py-3 text-[13px]">
                            @if ($log->status->value === 'sent')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-success-soft text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    مرسلة
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-danger-soft text-danger">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    فشلت
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[13px]">
                            @if ($log->error_code)
                                <span class="num text-danger font-semibold">{{ $log->error_code }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-muted">لا توجد رسائل</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
