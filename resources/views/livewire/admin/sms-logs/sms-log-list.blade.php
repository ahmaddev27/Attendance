<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">سجل الرسائل النصية</h1>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <select wire:model.live="status" class="border p-2 rounded">
            <option value="all">كل الحالات</option>
            <option value="sent">مرسلة</option>
            <option value="failed">فشلت</option>
        </select>

        <input type="text" wire:model.live.debounce.300ms="phone" placeholder="بحث برقم الجوال" class="border p-2 rounded" dir="ltr">

        <input type="date" wire:model.live="from" class="border p-2 rounded" placeholder="من تاريخ">
        <input type="date" wire:model.live="to" class="border p-2 rounded" placeholder="إلى تاريخ">
    </div>

    <table class="w-full border-collapse border">
        <thead class="bg-gray-100">
            <tr>
                <th class="border p-2">الوقت</th>
                <th class="border p-2">الجوال</th>
                <th class="border p-2">الرسالة</th>
                <th class="border p-2">الحالة</th>
                <th class="border p-2">الخطأ</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($logs as $log)
                <tr wire:key="sms-log-{{ $log->id }}">
                    <td class="border p-2 text-center">{{ $log->sent_at->format('Y-m-d H:i') }}</td>
                    <td class="border p-2 text-center" dir="ltr">{{ $log->phone }}</td>
                    <td class="border p-2 text-sm">{{ $log->message }}</td>
                    <td class="border p-2 text-center">
                        @switch($log->status->value)
                            @case('sent')
                                <span class="text-green-700">مرسلة</span>
                                @break
                            @case('failed')
                                <span class="text-red-700">فشلت</span>
                                @break
                        @endswitch
                    </td>
                    <td class="border p-2 text-center text-red-600" dir="ltr">{{ $log->error_code }}</td>
                </tr>
            @empty
                <tr>
                    <td class="border p-2 text-center text-gray-500" colspan="5">لا توجد رسائل</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
