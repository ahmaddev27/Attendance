<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">سجل الحضور</h1>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
        <input type="date" wire:model.live="from" class="border p-2 rounded" placeholder="من تاريخ">
        <input type="date" wire:model.live="to" class="border p-2 rounded" placeholder="إلى تاريخ">

        <select wire:model.live="employeeId" class="border p-2 rounded">
            <option value="">كل الموظفين</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->employee_number }})</option>
            @endforeach
        </select>

        <select wire:model.live="type" class="border p-2 rounded">
            <option value="all">حضور + انصراف</option>
            <option value="check_in">حضور</option>
            <option value="check_out">انصراف</option>
        </select>

        <select wire:model.live="fraudStatus" class="border p-2 rounded">
            <option value="all">كل حالات الفحص</option>
            <option value="passed">مقبول</option>
            <option value="skipped">بدون فحص</option>
            <option value="gps_failed">فشل فحص الموقع</option>
            <option value="ip_failed">فشل فحص IP</option>
        </select>
    </div>

    <table class="w-full border-collapse border">
        <thead class="bg-gray-100">
            <tr>
                <th class="border p-2">الوقت</th>
                <th class="border p-2">الموظف</th>
                <th class="border p-2">النوع</th>
                <th class="border p-2">IP</th>
                <th class="border p-2">حالة الفحص</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
                <tr wire:key="attendance-{{ $record->id }}">
                    <td class="border p-2 text-center">{{ $record->scanned_at->format('Y-m-d H:i') }}</td>
                    <td class="border p-2">{{ $record->employee->name }}</td>
                    <td class="border p-2 text-center">
                        {{ $record->type->value === 'check_in' ? 'حضور' : 'انصراف' }}
                    </td>
                    <td class="border p-2 text-center" dir="ltr">{{ $record->ip_address }}</td>
                    <td class="border p-2 text-center">
                        @switch($record->fraud_check_status->value)
                            @case('passed')
                                <span class="text-green-700">مقبول</span>
                                @break
                            @case('skipped')
                                <span class="text-gray-500">بدون فحص</span>
                                @break
                            @case('gps_failed')
                                <span class="text-red-700">فشل فحص الموقع</span>
                                @break
                            @case('ip_failed')
                                <span class="text-red-700">فشل فحص IP</span>
                                @break
                        @endswitch
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="border p-2 text-center text-gray-500" colspan="5">لا توجد سجلات حضور</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $records->links() }}</div>
</div>
