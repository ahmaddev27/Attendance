<div class="p-6 max-w-lg">
    <h1 class="text-2xl font-bold mb-4">
        {{ $employee?->exists ? 'تعديل موظف' : 'موظف جديد' }}
    </h1>

    <form wire:submit="save" class="space-y-4">
        <div>
            <label class="block mb-1">الاسم</label>
            <input type="text" wire:model="name" class="border p-2 rounded w-full">
            @error('name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block mb-1">الجوال</label>
            <input type="text" wire:model="phone" class="border p-2 rounded w-full" dir="ltr">
            @error('phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block mb-1">الإيميل (اختياري)</label>
            <input type="email" wire:model="email" class="border p-2 rounded w-full" dir="ltr">
            @error('email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" wire:model="isActive">
                <span>نشط</span>
            </label>
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">حفظ</button>
    </form>
</div>
