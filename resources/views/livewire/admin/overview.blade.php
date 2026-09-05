<div class="p-6">
    <h1 class="text-2xl font-bold mb-4">لوحة التحكم</h1>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 rounded shadow">
            <div class="text-sm text-gray-500">حضور اليوم</div>
            <div class="text-3xl font-bold">{{ $presentToday }} / {{ $activeEmployees }}</div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-sm text-gray-500">غياب اليوم</div>
            <div class="text-3xl font-bold">{{ $absentToday }}</div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-sm text-gray-500">إجازات معتمدة اليوم</div>
            <div class="text-3xl font-bold">{{ $approvedLeavesToday }}</div>
        </div>
        <div class="bg-white p-4 rounded shadow">
            <div class="text-sm text-gray-500">طلبات قيد المراجعة</div>
            <div class="text-3xl font-bold text-orange-600">{{ $pendingLeaves }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-4 rounded shadow">
            <h2 class="font-bold mb-3">آخر 7 أيام</h2>
            <div class="flex items-end gap-2 h-40">
                @foreach($last7Days as $day)
                    <div class="flex-1 flex flex-col items-center">
                        <div class="bg-blue-600 w-full" style="height: {{ min(100, $day['count'] * 10) }}%"></div>
                        <div class="text-xs mt-1">{{ $day['date'] }}</div>
                        <div class="text-xs font-bold">{{ $day['count'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="bg-white p-4 rounded shadow">
            <h2 class="font-bold mb-3">آخر عمليات المسح</h2>
            <ul class="space-y-2">
                @forelse($recentScans as $scan)
                    <li class="flex justify-between text-sm">
                        <span>{{ $scan->employee->name }}</span>
                        <span class="text-gray-500">{{ $scan->type->value }} — {{ $scan->scanned_at->format('H:i') }}</span>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">لا توجد عمليات مسح بعد</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
