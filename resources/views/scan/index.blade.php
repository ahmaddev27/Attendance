@extends('layouts.public')
@section('content')
    <h1 class="text-3xl font-bold text-center mb-8">{{ config('app.name') }}</h1>

    @if(session('success'))
        <div class="bg-green-100 text-green-800 p-4 rounded mb-4 text-center">
            @php $att = session('success'); @endphp
            {{ __('messages.scan_success_' . $att->type->value, ['time' => $att->scanned_at->format('H:i')]) }}
        </div>
    @endif

    @if(session('leave_success'))
        <div class="bg-purple-100 text-purple-800 p-4 rounded mb-4 text-center">
            تم إرسال طلب إجازتك. ستصلك رسالة SMS بالنتيجة.
        </div>
    @endif

    <div class="space-y-4">
        <a href="{{ route('scan.attendance.form') }}" class="block bg-blue-600 text-white text-center py-4 rounded-lg text-lg">
            تسجيل الحضور
        </a>
        {{-- TODO(Task 4.2): replace placeholder route with the real leave-request form --}}
        <a href="{{ route('scan.leave.form') }}" class="block bg-purple-600 text-white text-center py-4 rounded-lg text-lg">
            طلب إجازة
        </a>
    </div>
@endsection
