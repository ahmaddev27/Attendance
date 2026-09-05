@extends('layouts.public')
@section('content')
    <h1 class="text-2xl font-bold mb-6">تسجيل الحضور</h1>

    <form method="POST" action="{{ route('scan.attendance.preview') }}" x-data="{ lat: null, lng: null }" x-init="
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => { lat = pos.coords.latitude; lng = pos.coords.longitude; });
        }
    ">
        @csrf
        <input type="hidden" name="latitude" x-bind:value="lat">
        <input type="hidden" name="longitude" x-bind:value="lng">

        <label class="block mb-1">الرقم الوظيفي</label>
        <input type="number" name="employee_number" required autofocus
               class="border p-3 rounded w-full text-center text-xl" dir="ltr">

        @error('employee_number')
            <div class="text-red-600 text-sm mt-2">{{ $message }}</div>
        @enderror

        <button type="submit" class="mt-6 w-full bg-blue-600 text-white py-3 rounded-lg text-lg">
            متابعة
        </button>
    </form>
@endsection
