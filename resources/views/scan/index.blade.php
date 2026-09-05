@extends('layouts.public')
@section('content')
    <div class="text-center pt-6 pb-8">
        <x-brand-mark class="w-14 h-14 mb-3 mx-auto" />
        <h1 class="text-lg font-bold text-ink">{{ config('app.name') }}</h1>
        <p class="text-xs text-muted mt-0.5">نظام الحضور والإجازات</p>
    </div>

    @if(session('success'))
        @php $att = session('success'); @endphp
        <div class="bg-success-soft text-success border-r-4 border-success rounded-lg p-4 mb-4 text-center">
            <div class="text-sm font-semibold">
                {{ __('messages.scan_success_' . $att->type->value, ['time' => $att->scanned_at->format('H:i')]) }}
            </div>
        </div>
    @endif

    @if(session('leave_success'))
        <div class="bg-brand-soft text-brand-ink border-r-4 border-brand rounded-lg p-4 mb-4 text-center">
            <div class="text-sm font-semibold mb-0.5">تم إرسال طلب الإجازة</div>
            <div class="text-xs opacity-80">ستصلك رسالة SMS بالنتيجة قريباً</div>
        </div>
    @endif

    <div class="space-y-3">
        <a href="{{ route('scan.attendance.form') }}"
           class="block bg-brand hover:bg-brand-hover text-white text-center py-4 rounded-xl text-base font-semibold shadow transition">
            تسجيل الحضور
        </a>
        <a href="{{ route('scan.leave.form') }}"
           class="block bg-surface border border-hairline-strong text-ink text-center py-4 rounded-xl text-base font-semibold hover:bg-surface-2 transition">
            طلب إجازة
        </a>
    </div>

    <div class="text-center text-xs text-muted mt-8 num" x-data="{ now: new Date() }" x-init="setInterval(() => now = new Date(), 30000)">
        <span x-text="now.toLocaleDateString('ar-EG-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long' })"></span>
        ·
        <span x-text="now.toLocaleTimeString('ar-EG-u-nu-latn', { hour: '2-digit', minute: '2-digit', hour12: false })"></span>
    </div>
@endsection
