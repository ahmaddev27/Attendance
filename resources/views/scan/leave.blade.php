@extends('layouts.public')
@section('content')
    <h1 class="text-2xl font-bold mb-6">طلب إجازة</h1>

    <form method="POST" action="{{ route('scan.leave.submit') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block mb-1">الرقم الوظيفي</label>
            <input type="number" name="employee_number" value="{{ old('employee_number') }}"
                   required class="border p-3 rounded w-full text-center text-xl" dir="ltr">
            @error('employee_number') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block mb-1">من تاريخ</label>
            <input type="date" name="start_date" value="{{ old('start_date') }}" required class="border p-3 rounded w-full">
            @error('start_date') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block mb-1">إلى تاريخ</label>
            <input type="date" name="end_date" value="{{ old('end_date') }}" required class="border p-3 rounded w-full">
            @error('end_date') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="block mb-1">ملاحظة (اختياري)</label>
            <textarea name="note" class="border p-3 rounded w-full" rows="3">{{ old('note') }}</textarea>
        </div>

        <button type="submit" class="w-full bg-purple-600 text-white py-3 rounded-lg text-lg">
            إرسال الطلب
        </button>
    </form>
@endsection
