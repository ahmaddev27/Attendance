@extends('layouts.public')
@section('content')
    <div class="text-center pt-6 pb-6">
        <x-brand-mark class="w-12 h-12 mb-3 mx-auto" />
    </div>

    <h2 class="text-xl font-bold text-ink text-center mb-1">طلب إجازة</h2>
    <p class="text-sm text-muted text-center mb-8">سيصلك رد على الجوال</p>

    <form method="POST" action="{{ route('scan.leave.submit') }}" class="space-y-4">
        @csrf

        <div>
            <label class="block text-xs font-medium text-ink-2 mb-1.5">الرقم الوظيفي</label>
            <input type="number" name="employee_number" value="{{ old('employee_number') }}" required
                   class="w-full rounded-xl border border-hairline-strong bg-surface py-3.5 text-center text-lg font-bold num text-ink focus:border-brand focus:ring-1 focus:ring-brand"
                   dir="ltr">
            @error('employee_number')
                <div class="text-danger text-xs mt-1.5">{{ $message }}</div>
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-ink-2 mb-1.5">من تاريخ</label>
                <input type="date" name="start_date" value="{{ old('start_date') }}" required
                       class="w-full rounded-xl border border-hairline-strong bg-surface py-3 px-3 text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand">
                @error('start_date')
                    <div class="text-danger text-xs mt-1.5">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-ink-2 mb-1.5">إلى تاريخ</label>
                <input type="date" name="end_date" value="{{ old('end_date') }}" required
                       class="w-full rounded-xl border border-hairline-strong bg-surface py-3 px-3 text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand">
                @error('end_date')
                    <div class="text-danger text-xs mt-1.5">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-ink-2 mb-1.5">ملاحظة (اختياري)</label>
            <textarea name="note" rows="3"
                      class="w-full rounded-xl border border-hairline-strong bg-surface py-3 px-3.5 text-sm text-ink focus:border-brand focus:ring-1 focus:ring-brand resize-y">{{ old('note') }}</textarea>
            @error('note')
                <div class="text-danger text-xs mt-1.5">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit"
                class="w-full bg-brand hover:bg-brand-hover text-white py-4 rounded-xl text-base font-semibold shadow transition !mt-6">
            إرسال الطلب
        </button>
    </form>
@endsection
