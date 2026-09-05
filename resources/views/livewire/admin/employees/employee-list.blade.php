<div class="p-6">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-2xl font-bold">الموظفين</h1>
        <a href="{{ route('admin.employees.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded">+ موظف جديد</a>
    </div>

    @if (session('success'))
        <div class="bg-green-100 text-green-800 border border-green-300 rounded p-3 mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex gap-4 mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="بحث..." class="border p-2 rounded flex-1">
        <select wire:model.live="status" class="border p-2 rounded">
            <option value="all">الكل</option>
            <option value="active">نشط</option>
            <option value="inactive">معطّل</option>
        </select>
    </div>

    <table class="w-full border-collapse border">
        <thead class="bg-gray-100">
            <tr>
                <th class="border p-2">الرقم</th>
                <th class="border p-2">الاسم</th>
                <th class="border p-2">الجوال</th>
                <th class="border p-2">الحالة</th>
                <th class="border p-2">إجراءات</th>
            </tr>
        </thead>
        <tbody>
            @forelse($employees as $employee)
                <tr wire:key="employee-{{ $employee->id }}">
                    <td class="border p-2 text-center">{{ $employee->employee_number }}</td>
                    <td class="border p-2">{{ $employee->name }}</td>
                    <td class="border p-2">{{ $employee->phone }}</td>
                    <td class="border p-2 text-center">
                        {{ $employee->is_active ? 'نشط' : 'معطّل' }}
                    </td>
                    <td class="border p-2 text-center">
                        <a href="{{ route('admin.employees.edit', $employee) }}" class="text-blue-600">تعديل</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td class="border p-2 text-center text-gray-500" colspan="5">لا يوجد موظفون</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4">{{ $employees->links() }}</div>
</div>
