<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'TAQAT') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ground text-ink font-sans antialiased min-h-screen">
    <div class="min-h-screen grid place-items-center p-6">
        <div class="w-full max-w-md">
            <div class="flex justify-center mb-6">
                <a href="/" class="flex items-center gap-3">
                    <x-brand-mark class="w-11 h-11" />
                    <div>
                        <div class="text-lg font-bold text-ink">TAQAT</div>
                        <div class="text-xs text-muted">إدارة الحضور</div>
                    </div>
                </a>
            </div>
            <div class="bg-surface border border-hairline rounded-2xl shadow-lg p-8">
                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
