@php($isEditing = $employee?->exists)

<div>
    <div class="flex justify-between items-end pb-6 mb-6 border-b border-hairline">
        <div>
            <div class="text-xs text-muted mb-1">الموظفون / {{ $isEditing ? 'تعديل موظف' : 'موظف جديد' }}</div>
            <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">{{ $isEditing ? 'تعديل موظف' : 'موظف جديد' }}</h1>
        </div>
    </div>

    @if (session('success'))
        <div class="bg-success-soft text-success rounded-lg px-4 py-3 mb-6 text-sm font-medium">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-xl bg-surface border border-hairline rounded-xl p-6 md:p-8">
        <form wire:submit="save" class="space-y-5">
            <div>
                <label class="block text-[12.5px] font-semibold text-ink-2 mb-1.5">الاسم الكامل</label>
                <input type="text" wire:model="name" class="form-input" placeholder="مثال: سارة الأحمد">
                @error('name') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-[12.5px] font-semibold text-ink-2 mb-1.5">رقم الجوال</label>
                <input type="text" wire:model="phone" class="form-input" dir="ltr" style="text-align:right" placeholder="+962 79 123 4567">
                <p class="text-[11.5px] text-muted mt-1.5">سنرسل الرقم الوظيفي على هذا الرقم عبر SMS.</p>
                @error('phone') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-[12.5px] font-semibold text-ink-2 mb-1.5">
                    البريد الإلكتروني <span class="text-muted font-normal">(اختياري)</span>
                </label>
                <input type="email" wire:model="email" class="form-input" dir="ltr" style="text-align:right" placeholder="name@company.jo">
                @error('email') <p class="text-danger text-xs mt-1.5">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="inline-flex items-center gap-2 text-sm text-ink cursor-pointer">
                    <input type="checkbox" wire:model="isActive" class="rounded border-hairline-strong text-brand focus:ring-brand-soft">
                    حساب نشط ويستطيع التسجيل
                </label>
            </div>

            <div class="flex gap-2 pt-5 border-t border-hairline">
                <button type="submit" class="bg-brand hover:bg-brand-hover text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                    {{ $isEditing ? 'حفظ التغييرات' : 'حفظ وإرسال SMS' }}
                </button>
                <a href="{{ route('admin.employees.index') }}" class="bg-transparent border border-hairline-strong text-ink-2 hover:bg-surface-2 px-4 py-2 rounded-lg text-sm font-semibold transition">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>
