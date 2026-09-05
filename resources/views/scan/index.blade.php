@extends('layouts.public')
@section('content')
    <div class="text-center pt-6 pb-8" x-data="{ empNum: localStorage.getItem('taqat_employee_number') || null }">
        <div class="flex justify-center mb-4">
            <x-brand-logo class="h-12 w-auto" />
        </div>
        <p class="text-sm text-muted">نظام الحضور والإجازات</p>
        <div x-show="empNum" x-cloak class="mt-3 inline-flex items-center gap-2 text-xs bg-brand-soft text-brand-ink px-3 py-1.5 rounded-full">
            <span>الرقم الوظيفي: <span class="num font-semibold" x-text="empNum"></span></span>
            <button @click="localStorage.removeItem('taqat_employee_number'); empNum = null" class="text-brand-ink/60 hover:text-brand-ink" title="تغيير">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/></svg>
            </button>
        </div>
    </div>

    @if(session('success'))
        @php $att = session('success'); @endphp
        <div class="bg-success-soft text-success border-r-4 border-success rounded-lg p-4 mb-4 text-center">
            <div class="text-sm font-semibold mb-0.5">
                {{ $att->type->value === 'check_in' ? 'تم تسجيل الحضور' : 'تم تسجيل الانصراف' }}
            </div>
            <div class="text-xs opacity-80 num">{{ $att->scanned_at->format('H:i') }}</div>
        </div>
    @endif

    @if(session('leave_success'))
        <div class="bg-brand-soft text-brand-ink border-r-4 border-brand rounded-lg p-4 mb-4 text-center">
            <div class="text-sm font-semibold mb-0.5">تم إرسال طلب الإجازة</div>
            <div class="text-xs opacity-80">ستصلك رسالة SMS بالنتيجة قريباً</div>
        </div>
    @endif

    <div class="space-y-3" x-data="{ leaveOpen: false }">
        <a href="{{ route('scan.attendance.form') }}"
           class="block bg-brand hover:bg-brand-hover text-white text-center py-4 rounded-xl text-base font-semibold shadow transition">
            تسجيل الحضور
        </a>
        <button type="button" @click="leaveOpen = true"
                class="block w-full bg-surface border border-hairline-strong text-ink text-center py-4 rounded-xl text-base font-semibold hover:bg-surface-2 transition">
            طلب إجازة
        </button>

        {{-- Leave request modal --}}
        @include('scan.partials.leave-modal')
    </div>

    <div class="text-center text-xs text-muted mt-8 num" x-data="{ now: '' }" x-init="
        const upd = () => { now = new Date().toLocaleString('ar', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }); };
        upd(); setInterval(upd, 30000);
    ">
        <span x-text="now"></span>
    </div>
@endsection
