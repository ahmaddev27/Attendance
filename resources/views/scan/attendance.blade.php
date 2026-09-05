@extends('layouts.public')
@section('content')
    <div class="text-center pt-6 pb-6">
        <x-brand-mark class="w-12 h-12 mb-3 mx-auto" />
    </div>

    <h2 class="text-xl font-bold text-ink text-center mb-1">تسجيل الحضور</h2>
    <p class="text-sm text-muted text-center mb-8">أدخل رقمك الوظيفي</p>

    <form method="POST" action="{{ route('scan.attendance.preview') }}"
          x-data="{ lat: null, lng: null, locating: true, empNum: @js(old('employee_number')) || localStorage.getItem('taqat_employee_number') || '', submitting: false }"
          x-init="
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => { lat = pos.coords.latitude; lng = pos.coords.longitude; locating = false; },
                () => { locating = false; }
            );
        } else {
            locating = false;
        }
    "
          @submit="localStorage.setItem('taqat_employee_number', empNum); submitting = true">
        @csrf
        <input type="hidden" name="latitude" x-bind:value="lat">
        <input type="hidden" name="longitude" x-bind:value="lng">

        <div class="mb-6">
            <input type="number" name="employee_number" x-model="empNum" required autofocus
                   class="w-full rounded-xl border border-hairline-strong bg-surface py-4 text-center text-[22px] font-bold tracking-wider num text-ink focus:border-brand focus:ring-1 focus:ring-brand"
                   dir="ltr">

            @error('employee_number')
                <div class="text-danger text-sm mt-2 text-center">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" :disabled="submitting"
                class="w-full bg-brand hover:bg-brand-hover text-white py-4 rounded-xl text-base font-semibold shadow transition disabled:opacity-70 disabled:cursor-wait inline-flex items-center justify-center gap-2">
            <svg x-show="submitting" x-cloak class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"/>
                <path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
            </svg>
            <span x-text="submitting ? 'جاري المتابعة...' : 'متابعة'"></span>
        </button>

        <div class="flex items-center justify-center gap-1.5 text-xs text-muted mt-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="10" r="3" />
                <path d="M12 2a8 8 0 0 0-8 8c0 6 8 12 8 12s8-6 8-12a8 8 0 0 0-8-8z" />
            </svg>
            <span x-show="locating">جاري التحقّق من الموقع</span>
            <span x-show="!locating && lat">تم تحديد الموقع</span>
            <span x-show="!locating && !lat">تعذّر تحديد الموقع</span>
        </div>
    </form>
@endsection
