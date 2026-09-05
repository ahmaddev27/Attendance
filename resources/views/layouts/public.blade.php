<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'TAQAT') }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-ground text-ink font-sans antialiased min-h-screen">
    <div class="min-h-screen flex flex-col">
        <div class="flex-1 flex items-start justify-center px-4 py-8">
            <div class="w-full max-w-md">
                @yield('content')
            </div>
        </div>
        <footer class="text-center text-xs text-muted py-4">
            TAQAT · نظام الحضور
        </footer>
    </div>

    @livewireScripts
</body>
</html>
