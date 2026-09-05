<div>
    <div class="flex justify-between items-end pb-6 mb-6 border-b border-hairline">
        <div>
            <div class="text-xs text-muted mb-1">الموظفون</div>
            <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">قائمة الموظفين</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.employees.create') }}" class="bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                + موظف جديد
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="bg-success-soft text-success rounded-lg px-4 py-3 mb-6 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex flex-wrap gap-2.5 mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="بحث بالاسم أو الرقم أو الجوال…"
            class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface min-w-[240px] flex-1 max-w-sm placeholder:text-muted"
        >
        <select wire:model.live="status" class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface">
            <option value="all">الكل</option>
            <option value="active">نشط</option>
            <option value="inactive">معطّل</option>
        </select>
    </div>

    <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
        <table class="w-full border-collapse">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:90px">الرقم</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider">الاسم</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider">الجوال</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:110px">الحالة</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:80px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr wire:key="employee-{{ $employee->id }}" class="border-t border-hairline hover:bg-surface-2 transition">
                        <td class="px-4 py-3 text-[13px]"><span class="num">{{ $employee->employee_number }}</span></td>
                        <td class="px-4 py-3 text-[13px] font-medium text-ink">{{ $employee->name }}</td>
                        <td class="px-4 py-3 text-[13px]"><span class="num" dir="ltr">{{ $employee->phone }}</span></td>
                        <td class="px-4 py-3 text-[13px]">
                            @if ($employee->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-success-soft text-success">
                                    <span class="w-1.5 h-1.5 rounded-full bg-current"></span>
                                    نشط
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-surface-2 text-ink-2">
                                    معطّل
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-[13px] text-left">
                            <a href="{{ route('admin.employees.edit', $employee) }}" class="text-brand hover:text-brand-hover text-xs font-semibold">تعديل</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-muted">لا يوجد موظفون</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $employees->links() }}</div>
</div>
