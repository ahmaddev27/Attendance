<div x-show="leaveOpen" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm p-4"
     @click.self="leaveOpen = false"
     @keydown.escape.window="leaveOpen = false">
    <div class="bg-surface rounded-2xl w-full max-w-md p-6 shadow-lg"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 translate-y-6 sm:scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 sm:scale-95"
         x-data="{
            empNum: localStorage.getItem('taqat_employee_number') || '',
            hasStored: !!localStorage.getItem('taqat_employee_number'),
            submitting: false
         }">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h2 class="text-lg font-bold text-ink">طلب إجازة</h2>
                <p class="text-xs text-muted mt-0.5">
                    <template x-if="hasStored">
                        <span>للرقم الوظيفي <span class="num font-semibold" x-text="empNum"></span></span>
                    </template>
                    <template x-if="!hasStored">
                        <span>سيصلك الرد على الجوال</span>
                    </template>
                </p>
            </div>
            <button type="button" @click="leaveOpen = false" class="text-muted hover:text-ink p-1">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <form method="POST" action="{{ route('scan.leave.submit') }}" class="space-y-3"
              @submit="submitting = true">
            @csrf

            <template x-if="hasStored">
                <input type="hidden" name="employee_number" :value="empNum">
            </template>
            <template x-if="!hasStored">
                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1.5">الرقم الوظيفي</label>
                    <input type="number" name="employee_number" required x-model="empNum"
                           class="w-full px-3 py-2.5 border border-hairline-strong bg-surface rounded-lg text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none num" dir="ltr" style="text-align:center">
                    @error('employee_number') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
                </div>
            </template>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1.5">من تاريخ</label>
                    <input type="date" name="start_date" value="{{ old('start_date') }}" required
                           class="w-full px-3 py-2.5 border border-hairline-strong bg-surface rounded-lg text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none">
                    @error('start_date') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-2 mb-1.5">إلى تاريخ</label>
                    <input type="date" name="end_date" value="{{ old('end_date') }}" required
                           class="w-full px-3 py-2.5 border border-hairline-strong bg-surface rounded-lg text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none">
                    @error('end_date') <div class="text-xs text-danger mt-1">{{ $message }}</div> @enderror
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-ink-2 mb-1.5">ملاحظة <span class="text-muted font-normal">(اختياري)</span></label>
                <textarea name="note" rows="2"
                          class="w-full px-3 py-2.5 border border-hairline-strong bg-surface rounded-lg text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none">{{ old('note') }}</textarea>
            </div>

            <div class="pt-2">
                <button type="submit" :disabled="submitting"
                        class="w-full bg-brand hover:bg-brand-hover text-white py-3 rounded-lg text-sm font-semibold shadow transition disabled:opacity-70 disabled:cursor-wait inline-flex items-center justify-center gap-2">
                    <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                        <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                    </svg>
                    <span x-text="submitting ? 'جاري الإرسال...' : 'إرسال الطلب'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
