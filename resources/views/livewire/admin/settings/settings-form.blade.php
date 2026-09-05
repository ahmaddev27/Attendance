<form wire:submit="save">
    <div class="flex justify-between items-end pb-6 mb-6 border-b border-hairline">
        <div>
            <div class="text-xs text-muted mb-1">الإعدادات</div>
            <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">إعدادات النظام</h1>
        </div>
        <div class="flex gap-2">
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                    class="inline-flex items-center gap-2 bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold shadow transition disabled:opacity-70 disabled:cursor-wait">
                <svg wire:loading wire:target="save" class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                    <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span wire:loading.remove wire:target="save">حفظ التغييرات</span>
                <span wire:loading wire:target="save">جاري الحفظ...</span>
            </button>
        </div>
    </div>

    <div class="bg-surface border border-hairline rounded-xl divide-y divide-hairline">
        {{-- GPS check --}}
        <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-6 md:gap-8 p-6">
            <div>
                <h3 class="text-sm font-bold text-ink mb-1">فحص الموقع الجغرافي</h3>
                <p class="text-xs text-muted leading-relaxed">يرفض التسجيل إذا كان الموظف خارج نطاق المكتب.</p>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <label class="text-sm text-ink">تفعيل فحص GPS</label>
                    <label class="relative inline-flex items-center shrink-0 cursor-pointer">
                        <input type="checkbox" wire:model="gpsEnabled" class="peer sr-only">
                        <span class="w-9 h-5 rounded-full bg-hairline-strong peer-checked:bg-brand transition-colors"></span>
                        <span class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-white transition-transform peer-checked:-translate-x-4"></span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[12px] font-semibold text-ink-2 mb-1.5">خط العرض</label>
                        <input type="number" step="0.0000001" wire:model="officeLat" class="form-input num" dir="ltr" style="text-align:right">
                        @error('officeLat') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-ink-2 mb-1.5">خط الطول</label>
                        <input type="number" step="0.0000001" wire:model="officeLng" class="form-input num" dir="ltr" style="text-align:right">
                        @error('officeLng') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-[12px] font-semibold text-ink-2 mb-1.5">نصف قطر السماحية (متر)</label>
                        <input type="number" wire:model="geofenceRadius" min="1" class="form-input num" dir="ltr" style="text-align:right">
                        @error('geofenceRadius') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- IP check --}}
        <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-6 md:gap-8 p-6">
            <div>
                <h3 class="text-sm font-bold text-ink mb-1">فحص عنوان IP</h3>
                <p class="text-xs text-muted leading-relaxed">يقبل التسجيل فقط من شبكات محدّدة.</p>
            </div>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <label class="text-sm text-ink">تفعيل فحص IP</label>
                    <label class="relative inline-flex items-center shrink-0 cursor-pointer">
                        <input type="checkbox" wire:model="ipEnabled" class="peer sr-only">
                        <span class="w-9 h-5 rounded-full bg-hairline-strong peer-checked:bg-brand transition-colors"></span>
                        <span class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-white transition-transform peer-checked:-translate-x-4"></span>
                    </label>
                </div>

                <div>
                    <label class="block text-[12px] font-semibold text-ink-2 mb-1.5">قائمة عناوين IP المسموح بها</label>
                    <textarea
                        wire:model="ipWhitelistText"
                        rows="4"
                        class="form-input font-mono"
                        dir="ltr"
                        style="text-align:right"
                        placeholder="192.168.1.100"
                    ></textarea>
                    <p class="text-[11.5px] text-muted mt-1.5">عنوان أو مدى واحد في كل سطر.</p>
                    @error('ipWhitelistText') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- MTC SMS provider --}}
        <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-6 md:gap-8 p-6">
            <div>
                <h3 class="text-sm font-bold text-ink mb-1">بيانات اعتماد MTC SMS</h3>
                <p class="text-xs text-muted leading-relaxed">بيانات الاتصال بواجهة الإرسال. تُخزَّن مشفّرة.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[12px] font-semibold text-ink-2 mb-1.5">اسم المستخدم</label>
                    <input type="text" wire:model="smsUsername" class="form-input" dir="ltr" style="text-align:right">
                    @error('smsUsername') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-ink-2 mb-1.5">كلمة السر</label>
                    <input type="password" wire:model="smsPassword" class="form-input" dir="ltr" style="text-align:right" autocomplete="new-password">
                    @error('smsPassword') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[12px] font-semibold text-ink-2 mb-1.5">اسم المرسل</label>
                    <input type="text" wire:model="smsSender" class="form-input" dir="ltr" style="text-align:right">
                    @error('smsSender') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        {{-- Employee numbering --}}
        <div class="grid grid-cols-1 md:grid-cols-[220px_1fr] gap-6 md:gap-8 p-6">
            <div>
                <h3 class="text-sm font-bold text-ink mb-1">ترقيم الموظفين</h3>
                <p class="text-xs text-muted leading-relaxed">يُستخدم فقط عندما تكون قاعدة البيانات فارغة.</p>
            </div>
            <div class="max-w-[220px]">
                <label class="block text-[12px] font-semibold text-ink-2 mb-1.5">الرقم الوظيفي الابتدائي</label>
                <input type="number" wire:model="employeeNumberStart" min="1" class="form-input num" dir="ltr" style="text-align:right">
                @error('employeeNumberStart') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>
</form>
