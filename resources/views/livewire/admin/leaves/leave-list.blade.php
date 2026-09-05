<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">طلبات الإجازة</h1>

    @if (session('success'))
        <div class="bg-green-100 text-green-800 border border-green-300 rounded p-3 mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <select wire:model.live="status" class="border p-2 rounded">
            <option value="all">كل الحالات</option>
            <option value="pending">قيد المراجعة</option>
            <option value="approved">مقبولة</option>
            <option value="rejected">مرفوضة</option>
        </select>

        <input type="date" wire:model.live="from" class="border p-2 rounded" placeholder="من تاريخ">
        <input type="date" wire:model.live="to" class="border p-2 rounded" placeholder="إلى تاريخ">

        <select wire:model.live="employeeId" class="border p-2 rounded">
            <option value="">كل الموظفين</option>
            @foreach ($employees as $employee)
                <option value="{{ $employee->id }}">{{ $employee->name }} ({{ $employee->employee_number }})</option>
            @endforeach
        </select>
    </div>

    <table class="w-full border-collapse border">
        <thead class="bg-gray-100">
            <tr>
                <th class="border p-2">الموظف</th>
                <th class="border p-2">من</th>
                <th class="border p-2">إلى</th>
                <th class="border p-2">ملاحظة</th>
                <th class="border p-2">الحالة</th>
                <th class="border p-2">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($leaves as $leave)
                <tr wire:key="leave-{{ $leave->id }}">
                    <td class="border p-2">{{ $leave->employee->name }}</td>
                    <td class="border p-2 text-center">{{ $leave->start_date->format('Y-m-d') }}</td>
                    <td class="border p-2 text-center">{{ $leave->end_date->format('Y-m-d') }}</td>
                    <td class="border p-2 text-sm">{{ $leave->note }}</td>
                    <td class="border p-2 text-center">
                        @switch($leave->status->value)
                            @case('pending')
                                <span class="text-yellow-700">قيد المراجعة</span>
                                @break
                            @case('approved')
                                <span class="text-green-700">مقبولة</span>
                                @break
                            @case('rejected')
                                <span class="text-red-700">مرفوضة</span>
                                @break
                        @endswitch
                    </td>
                    <td class="border p-2 text-center">
                        @if ($leave->status->value === 'pending')
                            <button
                                type="button"
                                wire:click="approve({{ $leave->id }})"
                                wire:confirm="هل تريد الموافقة على هذه الإجازة؟"
                                class="text-green-600 hover:underline"
                            >قبول</button>
                            <button
                                type="button"
                                wire:click="startReject({{ $leave->id }})"
                                class="text-red-600 hover:underline mx-2"
                            >رفض</button>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="border p-2 text-center text-gray-500" colspan="6">لا توجد طلبات إجازة</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $leaves->links() }}</div>

    @if ($rejectingId)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white p-6 rounded max-w-md w-full">
                <h2 class="text-xl font-bold mb-3">سبب الرفض</h2>

                <textarea
                    wire:model="rejectReason"
                    class="border p-2 rounded w-full"
                    rows="3"
                    placeholder="اكتب سبب رفض طلب الإجازة"
                ></textarea>
                @error('rejectReason')
                    <div class="text-red-600 text-sm mt-1">{{ $message }}</div>
                @enderror

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" wire:click="cancelReject" class="px-4 py-2 rounded border">إلغاء</button>
                    <button type="button" wire:click="confirmReject" class="bg-red-600 text-white px-4 py-2 rounded">
                        تأكيد الرفض
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
