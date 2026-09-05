<div>
    <div class="flex justify-between items-end pb-6 mb-6 border-b border-hairline">
        <div>
            <div class="text-xs text-muted mb-1">الإجازات</div>
            <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">طلبات الإجازة</h1>
        </div>
    </div>

    <div class="flex flex-wrap gap-2.5 mb-4">
        <select wire:model.live="status" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">
            <option value="all">كل الحالات</option>
            <option value="pending">قيد المراجعة</option>
            <option value="approved">مقبولة</option>
            <option value="rejected">مرفوضة</option>
        </select>

        <input type="date" wire:model.live="from" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">
        <input type="date" wire:model.live="to" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">

        <select wire:model.live="employeeId" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">
            <option value="">كل الموظفين</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->employee_number }})</option>
            @endforeach
        </select>
    </div>

    <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
        <table class="w-full border-collapse">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider">الموظف</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:120px">من</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:120px">إلى</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider">الملاحظة</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:130px">الحالة</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:190px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leaves as $leave)
                    <tr wire:key="leave-{{ $leave->id }}" class="border-t border-hairline hover:bg-surface-2 transition">
                        <td class="px-4 py-3 text-[13px]">
                            <div class="font-medium text-ink">{{ $leave->employee->name }}</div>
                            <div class="text-[11px] text-muted num">#{{ $leave->employee->employee_number }}</div>
                        </td>
                        <td class="px-4 py-3 text-[13px]"><span class="num">{{ $leave->start_date->format('Y-m-d') }}</span></td>
                        <td class="px-4 py-3 text-[13px]"><span class="num">{{ $leave->end_date->format('Y-m-d') }}</span></td>
                        <td class="px-4 py-3 text-[13px] text-ink-2">{{ $leave->note ?: '—' }}</td>
                        <td class="px-4 py-3 text-[13px]">
                            @switch($leave->status->value)
                                @case('pending')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-warn-soft text-warn">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        قيد المراجعة
                                    </span>
                                    @break
                                @case('approved')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-success-soft text-success">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        مقبولة
                                    </span>
                                    @break
                                @case('rejected')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-danger-soft text-danger">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                        مرفوضة
                                    </span>
                                    @break
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-[13px] text-left">
                            @if ($leave->status->value === 'pending')
                                <div class="flex gap-2 justify-end">
                                    <button
                                        type="button"
                                        wire:click="startApprove({{ $leave->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="startApprove({{ $leave->id }})"
                                        class="inline-flex items-center gap-1.5 bg-brand hover:bg-brand-hover text-white px-3 py-1.5 rounded-lg text-xs font-semibold transition disabled:opacity-70 disabled:cursor-wait"
                                    >
                                        <svg wire:loading wire:target="startApprove({{ $leave->id }})" class="animate-spin w-3 h-3" viewBox="0 0 24 24" fill="none">
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                                            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                                        </svg>
                                        قبول
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="startReject({{ $leave->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="startReject({{ $leave->id }})"
                                        class="bg-transparent border border-hairline-strong text-ink-2 hover:bg-surface-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition disabled:opacity-70 disabled:cursor-wait"
                                    >رفض</button>
                                </div>
                            @elseif ($leave->status->value === 'approved')
                                <div class="text-[11px] text-muted">
                                    اعتمدها {{ $leave->reviewer?->name ?? '—' }}
                                    @if ($leave->reviewed_at)
                                        <span class="num">· {{ $leave->reviewed_at->diffForHumans() }}</span>
                                    @endif
                                </div>
                            @else
                                <div class="text-[11px] text-muted">
                                    {{ $leave->rejection_reason ? str($leave->rejection_reason)->limit(30) : 'مرفوضة' }}
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-muted">لا توجد طلبات إجازة</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $leaves->links() }}</div>

    @if ($approvingId)
        @php $approvingLeave = $leaves->firstWhere('id', $approvingId); @endphp
        <div
            wire:transition:enter="transition ease-out duration-200"
            wire:transition:enter-start="opacity-0"
            wire:transition:enter-end="opacity-100"
            wire:transition:leave="transition ease-in duration-150"
            wire:transition:leave-start="opacity-100"
            wire:transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            wire:click.self="cancelApprove"
            wire:keydown.escape.window="cancelApprove"
        >
            <div
                wire:transition:enter="transition ease-out duration-250"
                wire:transition:enter-start="opacity-0 translate-y-4 scale-95"
                wire:transition:enter-end="opacity-100 translate-y-0 scale-100"
                wire:transition:leave="transition ease-in duration-150"
                wire:transition:leave-start="opacity-100 translate-y-0 scale-100"
                wire:transition:leave-end="opacity-0 translate-y-4 scale-95"
                class="bg-surface rounded-2xl w-full max-w-md p-6 shadow-lg"
            >
                <div class="w-11 h-11 rounded-full bg-success-soft text-success grid place-items-center mb-4">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6L9 17l-5-5"/>
                    </svg>
                </div>

                <h2 class="text-base font-bold text-ink mb-1">تأكيد الموافقة</h2>
                <p class="text-[13px] text-muted mb-4">
                    هل أنت متأكد من الموافقة على طلب الإجازة؟
                    @if ($approvingLeave)
                        <span class="block mt-1.5 text-ink-2 font-medium">
                            {{ $approvingLeave->employee->name }}
                            <span class="num">· {{ $approvingLeave->start_date->format('Y-m-d') }} إلى {{ $approvingLeave->end_date->format('Y-m-d') }}</span>
                        </span>
                    @endif
                </p>

                <div class="flex gap-2 justify-start mt-5">
                    <button type="button" wire:click="confirmApprove"
                            wire:loading.attr="disabled" wire:target="confirmApprove"
                            class="inline-flex items-center gap-1.5 bg-success hover:opacity-90 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-70 disabled:cursor-wait">
                        <svg wire:loading wire:target="confirmApprove" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        تأكيد الموافقة
                    </button>
                    <button type="button" wire:click="cancelApprove" class="bg-transparent border border-hairline-strong text-ink-2 hover:bg-surface-2 px-4 py-2 rounded-lg text-sm font-semibold transition">
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    @endif

    @if ($rejectingId)
        <div
            wire:transition:enter="transition ease-out duration-200"
            wire:transition:enter-start="opacity-0"
            wire:transition:enter-end="opacity-100"
            wire:transition:leave="transition ease-in duration-150"
            wire:transition:leave-start="opacity-100"
            wire:transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            wire:click.self="cancelReject"
            wire:keydown.escape.window="cancelReject"
        >
            <div
                wire:transition:enter="transition ease-out duration-250"
                wire:transition:enter-start="opacity-0 translate-y-4 scale-95"
                wire:transition:enter-end="opacity-100 translate-y-0 scale-100"
                wire:transition:leave="transition ease-in duration-150"
                wire:transition:leave-start="opacity-100 translate-y-0 scale-100"
                wire:transition:leave-end="opacity-0 translate-y-4 scale-95"
                class="bg-surface rounded-2xl w-full max-w-md p-6 shadow-lg"
            >
                <h2 class="text-base font-bold text-ink mb-1">سبب الرفض</h2>
                <p class="text-[13px] text-muted mb-4">سيصل هذا السبب للموظف عبر SMS، وسيُحفظ في سجل الطلب.</p>

                <textarea
                    wire:model="rejectReason"
                    rows="3"
                    class="form-input"
                    placeholder="اكتب سبب رفض طلب الإجازة"
                ></textarea>
                @error('rejectReason')
                    <p class="text-danger text-xs mt-1.5">{{ $message }}</p>
                @enderror

                <div class="flex gap-2 justify-start mt-5">
                    <button type="button" wire:click="confirmReject"
                            wire:loading.attr="disabled" wire:target="confirmReject"
                            class="inline-flex items-center gap-1.5 bg-danger hover:opacity-90 text-white px-4 py-2 rounded-lg text-sm font-semibold transition disabled:opacity-70 disabled:cursor-wait">
                        <svg wire:loading wire:target="confirmReject" class="animate-spin w-3.5 h-3.5" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        تأكيد الرفض
                    </button>
                    <button type="button" wire:click="cancelReject" class="bg-transparent border border-hairline-strong text-ink-2 hover:bg-surface-2 px-4 py-2 rounded-lg text-sm font-semibold transition">
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
