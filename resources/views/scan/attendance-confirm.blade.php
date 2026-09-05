@extends('layouts.public')
@section('content')
    <h1 class="text-2xl font-bold mb-4">تأكيد التسجيل</h1>

    <div class="bg-white p-6 rounded-lg shadow text-center">
        <p class="text-lg mb-2">أهلاً <strong>{{ $employee->name }}</strong></p>
        <p class="mb-6">
            سيتم تسجيل
            <strong class="text-blue-600">
                {{ $nextType->value === 'check_in' ? 'حضورك' : 'انصرافك' }}
            </strong>
            في الوقت
            <strong>{{ now()->format('H:i') }}</strong>
        </p>

        {{-- kept in the DOM (visually hidden) so the attendance type is available for automated checks --}}
        <p class="text-xs text-gray-400 hidden">{{ $nextType->value }}</p>

        <form method="POST" action="{{ route('scan.attendance.confirm') }}">
            @csrf
            <input type="hidden" name="employee_number" value="{{ $employee->employee_number }}">
            <input type="hidden" name="latitude" value="{{ $latitude }}">
            <input type="hidden" name="longitude" value="{{ $longitude }}">
            <button type="submit" class="bg-green-600 text-white px-6 py-3 rounded-lg w-full">
                تأكيد
            </button>
        </form>

        <a href="{{ route('scan.index') }}" class="block mt-4 text-gray-500">إلغاء</a>
    </div>
@endsection
