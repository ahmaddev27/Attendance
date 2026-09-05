<div class="p-6 max-w-3xl">
    <h1 class="text-2xl font-bold mb-4">الإعدادات</h1>

    @if (session('success'))
        <div class="bg-green-100 text-green-800 p-3 mb-3 rounded">{{ session('success') }}</div>
    @endif

    <form wire:submit="save" class="space-y-6">
        <fieldset class="border p-4 rounded">
            <legend class="font-bold px-1">فحص الموقع الجغرافي (GPS)</legend>

            <label class="inline-flex items-center gap-2 mb-3">
                <input type="checkbox" wire:model="gpsEnabled">
                <span>تفعيل فحص الموقع الجغرافي</span>
            </label>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block mb-1 text-sm">خط العرض</label>
                    <input type="number" step="0.0000001" wire:model="officeLat" class="border p-2 rounded w-full" dir="ltr">
                    @error('officeLat') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block mb-1 text-sm">خط الطول</label>
                    <input type="number" step="0.0000001" wire:model="officeLng" class="border p-2 rounded w-full" dir="ltr">
                    @error('officeLng') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block mb-1 text-sm">نصف قطر السماحية (متر)</label>
                    <input type="number" wire:model="geofenceRadius" class="border p-2 rounded w-full" min="1">
                    @error('geofenceRadius') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="border p-4 rounded">
            <legend class="font-bold px-1">فحص عنوان IP</legend>

            <label class="inline-flex items-center gap-2 mb-3">
                <input type="checkbox" wire:model="ipEnabled">
                <span>تفعيل فحص عنوان IP</span>
            </label>

            <label class="block mb-1 text-sm">قائمة عناوين IP المسموح بها (عنوان واحد في كل سطر)</label>
            <textarea wire:model="ipWhitelistText" rows="4" class="border p-2 rounded w-full font-mono" dir="ltr"
                placeholder="192.168.1.100"></textarea>
            @error('ipWhitelistText') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
        </fieldset>

        <fieldset class="border p-4 rounded">
            <legend class="font-bold px-1">بيانات اعتماد MTC SMS</legend>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block mb-1 text-sm">اسم المستخدم</label>
                    <input type="text" wire:model="smsUsername" class="border p-2 rounded w-full" dir="ltr">
                    @error('smsUsername') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block mb-1 text-sm">كلمة السر</label>
                    <input type="password" wire:model="smsPassword" class="border p-2 rounded w-full" dir="ltr" autocomplete="new-password">
                    @error('smsPassword') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block mb-1 text-sm">اسم المرسل</label>
                    <input type="text" wire:model="smsSender" class="border p-2 rounded w-full" dir="ltr">
                    @error('smsSender') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>
        </fieldset>

        <fieldset class="border p-4 rounded">
            <legend class="font-bold px-1">ترقيم الموظفين</legend>

            <label class="block mb-1 text-sm">الرقم الوظيفي الابتدائي</label>
            <input type="number" wire:model="employeeNumberStart" class="border p-2 rounded w-full md:w-48" min="1">
            @error('employeeNumberStart') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            <p class="text-sm text-gray-500 mt-1">يُستخدم فقط عند إنشاء أول موظف، أي حين يكون جدول الموظفين فارغاً.</p>
        </fieldset>

        <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded">حفظ الإعدادات</button>
    </form>
</div>
