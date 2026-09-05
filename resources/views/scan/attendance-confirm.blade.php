@extends('layouts.public')
@section('content')
    <div class="text-center pt-6 pb-6">
        <x-brand-logo class="h-12 w-auto mb-3 mx-auto" />
    </div>

    <h2 class="text-xl font-bold text-ink text-center mb-1">تأكيد التسجيل</h2>
    <p class="text-sm text-muted text-center mb-6">راجع البيانات قبل الحفظ</p>

    <div class="bg-surface border border-hairline rounded-xl p-6 mb-5 text-center">
        <div class="text-sm text-muted mb-0.5">أهلاً</div>
        <div class="text-lg font-bold text-ink mb-4">{{ $employee->name }}</div>

        <div class="bg-brand-soft text-brand-ink rounded-lg py-3 px-4 text-sm mb-4">
            سيتم تسجيل
            <strong>{{ $nextType->value === 'check_in' ? 'حضورك' : 'انصرافك' }}</strong>
        </div>

        {{-- kept in the DOM (visually hidden) so the attendance type is available for automated checks --}}
        <p class="text-xs text-gray-400 hidden">{{ $nextType->value }}</p>

        <div class="text-3xl font-extrabold text-ink num tracking-wider">{{ now()->format('H:i') }}</div>
        <div class="text-xs text-muted mt-1">{{ now()->translatedFormat('l j F Y') }}</div>
    </div>

    <form method="POST" action="{{ route('scan.attendance.confirm') }}"
          x-data="{ submitting: false }"
          @submit="submitting = true">
        @csrf
        <input type="hidden" name="employee_number" value="{{ $employee->employee_number }}">
        <input type="hidden" name="latitude" value="{{ $latitude }}">
        <input type="hidden" name="longitude" value="{{ $longitude }}">

        <button type="submit" :disabled="submitting"
                class="w-full bg-brand hover:bg-brand-hover text-white py-4 rounded-xl text-base font-semibold shadow transition disabled:opacity-70 disabled:cursor-wait inline-flex items-center justify-center gap-2">
            <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span x-text="submitting ? 'جاري الحفظ...' : 'تأكيد الحفظ'"></span>
        </button>
    </form>

    <a href="{{ route('scan.index') }}"
       class="block mt-3 text-center text-muted text-sm py-3 hover:text-ink transition">
        إلغاء
    </a>
@endsection
