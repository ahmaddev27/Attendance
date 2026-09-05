<div>
    <div class="flex justify-between items-end pb-6 mb-6 border-b border-hairline">
        <div>
            <div class="text-xs text-muted mb-1">الموظفون</div>
            <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">قائمة الموظفين</h1>
        </div>
        <div class="flex gap-2">
            <button type="button" wire:click="openCreate" class="bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                + موظف جديد
            </button>
        </div>
    </div>

    <div class="flex flex-wrap gap-2.5 mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="بحث بالاسم أو الرقم أو الجوال…"
            class="px-3 py-2 text-sm border border-hairline-strong rounded-lg bg-surface min-w-[240px] flex-1 max-w-sm placeholder:text-muted"
        >
        <div wire:ignore class="min-w-[160px]">
            <select data-search wire:model.live="status" class="w-full">
                <option value="all">الكل</option>
                <option value="active">نشط</option>
                <option value="inactive">معطّل</option>
            </select>
        </div>
    </div>

    <div class="bg-surface border border-hairline rounded-xl overflow-hidden">
        <table class="w-full border-collapse">
            <thead class="bg-surface-2">
                <tr>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:90px">الرقم</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider">الاسم</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider">الجوال</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:110px">الحالة</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold text-muted uppercase tracking-wider" style="width:100px">إجراءات</th>
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
                            <div class="inline-flex items-center gap-1">
                                <button type="button" wire:click="openEdit({{ $employee->id }})"
                                        title="تعديل"
                                        class="p-1.5 text-ink-2 hover:text-brand hover:bg-brand-soft rounded-md transition">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button type="button" wire:click="startDelete({{ $employee->id }})"
                                        title="حذف"
                                        class="p-1.5 text-ink-2 hover:text-danger hover:bg-danger-soft rounded-md transition">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/></svg>
                                </button>
                            </div>
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

    {{-- Create / edit modal --}}
    @if ($showModal)
        <div
            wire:transition:enter="transition ease-out duration-200"
            wire:transition:enter-start="opacity-0"
            wire:transition:enter-end="opacity-100"
            wire:transition:leave="transition ease-in duration-150"
            wire:transition:leave-start="opacity-100"
            wire:transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            wire:click.self="closeModal"
            wire:keydown.escape.window="closeModal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="employee-modal-title"
        >
            <div
                wire:transition:enter="transition ease-out duration-250"
                wire:transition:enter-start="opacity-0 translate-y-4 scale-95"
                wire:transition:enter-end="opacity-100 translate-y-0 scale-100"
                wire:transition:leave="transition ease-in duration-150"
                wire:transition:leave-start="opacity-100 translate-y-0 scale-100"
                wire:transition:leave-end="opacity-0 translate-y-4 scale-95"
                class="bg-surface rounded-2xl w-full max-w-lg p-6 shadow-lg"
            >
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h2 id="employee-modal-title" class="text-lg font-bold text-ink">
                            {{ $editingId ? 'تعديل موظف' : 'موظف جديد' }}
                        </h2>
                        <p class="text-xs text-muted mt-0.5">
                            {{ $editingId ? 'حدّث بيانات الموظف' : 'سيتم إرسال الرقم الوظيفي على الجوال عبر SMS' }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeModal" class="text-muted hover:text-ink p-2.5 -m-2 rounded-lg">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="save" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-ink-2 mb-1.5">الاسم الكامل</label>
                        <input type="text" wire:model="name" placeholder="مثال: سارة الأحمد"
                               class="w-full px-3 py-2.5 border border-hairline-strong bg-surface rounded-lg text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none">
                        @error('name') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-2 mb-1.5">رقم الجوال</label>
                        <input type="text" wire:model="phone" placeholder="+962 79 123 4567" dir="ltr" style="text-align:right"
                               class="w-full px-3 py-2.5 border border-hairline-strong bg-surface rounded-lg text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none">
                        <div class="text-[11px] text-muted mt-1">سنرسل الرقم الوظيفي على هذا الرقم.</div>
                        @error('phone') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-ink-2 mb-1.5">
                            البريد الإلكتروني <span class="text-muted font-normal">(اختياري)</span>
                        </label>
                        <input type="email" wire:model="email" placeholder="name@example.com" dir="ltr" style="text-align:right"
                               class="w-full px-3 py-2.5 border border-hairline-strong bg-surface rounded-lg text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none">
                        @error('email') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
                    </div>

                    <div>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="isActive"
                                   class="rounded border-hairline-strong text-brand focus:ring-brand">
                            <span>حساب نشط ويستطيع التسجيل</span>
                        </label>
                    </div>

                    <div class="flex gap-2 pt-4 border-t border-hairline mt-4">
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="save"
                                class="inline-flex items-center gap-2 bg-brand hover:bg-brand-hover text-white px-4 py-2.5 rounded-lg text-sm font-semibold shadow transition disabled:opacity-70 disabled:cursor-wait">
                            <svg wire:loading wire:target="save" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                            </svg>
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'حفظ التغييرات' : 'حفظ وإرسال SMS' }}</span>
                            <span wire:loading wire:target="save">جاري الحفظ...</span>
                        </button>
                        <button type="button" wire:click="closeModal"
                                class="inline-flex items-center px-4 py-2.5 bg-transparent border border-hairline-strong text-ink-2 hover:bg-surface-2 rounded-lg text-sm font-semibold transition">
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Delete confirmation modal --}}
    @if ($deletingId)
        @php $deletingEmployee = $employees->firstWhere('id', $deletingId); @endphp
        <div
            wire:transition:enter="transition ease-out duration-200"
            wire:transition:enter-start="opacity-0"
            wire:transition:enter-end="opacity-100"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
            wire:click.self="cancelDelete"
            wire:keydown.escape.window="cancelDelete"
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-modal-title"
        >
            <div
                wire:transition:enter="transition ease-out duration-250"
                wire:transition:enter-start="opacity-0 translate-y-4 scale-95"
                wire:transition:enter-end="opacity-100 translate-y-0 scale-100"
                class="bg-surface rounded-2xl w-full max-w-md p-6 shadow-lg"
            >
                <div class="flex items-start gap-4 mb-4">
                    <div class="w-10 h-10 rounded-full bg-danger-soft text-danger grid place-items-center shrink-0">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                    </div>
                    <div class="flex-1">
                        <h3 id="delete-modal-title" class="text-lg font-bold text-ink mb-1">تأكيد حذف الموظف</h3>
                        <p class="text-sm text-muted">
                            هل أنت متأكد من حذف الموظف؟ سيتم حذف كل بيانات الحضور والإجازات المرتبطة به. هذه العملية لا يمكن التراجع عنها.
                        </p>
                        @if ($deletingEmployee)
                            <div class="inline-flex items-center gap-2 mt-3 px-3 py-1.5 bg-surface-2 rounded-lg text-sm">
                                <span class="font-semibold text-ink">{{ $deletingEmployee->name }}</span>
                                <span class="text-muted num">#{{ $deletingEmployee->employee_number }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4 border-t border-hairline">
                    <button type="button" wire:click="cancelDelete"
                            class="inline-flex items-center px-4 py-2 bg-transparent border border-hairline-strong text-ink-2 hover:bg-surface-2 rounded-lg text-sm font-semibold transition">
                        إلغاء
                    </button>
                    <button type="button" wire:click="confirmDelete"
                            wire:loading.attr="disabled"
                            wire:target="confirmDelete"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-danger hover:bg-danger text-white rounded-lg text-sm font-semibold shadow transition disabled:opacity-70 disabled:cursor-wait">
                        <svg wire:loading wire:target="confirmDelete" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                            <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                        </svg>
                        <span wire:loading.remove wire:target="confirmDelete">تأكيد الحذف</span>
                        <span wire:loading wire:target="confirmDelete">جاري الحذف...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
