<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'TAQAT') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-ground text-ink font-sans antialiased min-h-screen" x-data="{ mobileNav: false }">
    <div class="flex min-h-screen">
        {{-- Sidebar --}}
        <aside class="hidden md:flex fixed inset-y-0 right-0 w-60 bg-surface border-l border-hairline flex-col p-4 gap-6 z-30">
            {{-- Brand --}}
            <a href="{{ route('admin.overview') }}" class="flex items-center gap-3 px-2 py-1">
                <x-brand-mark class="w-10 h-10" />
                <div>
                    <div class="text-sm font-bold text-ink">TAQAT</div>
                    <div class="text-[11px] text-muted">إدارة الحضور</div>
                </div>
            </a>

            {{-- Nav --}}
            <nav class="flex flex-col gap-0.5">
                @include('partials.admin-nav')
            </nav>

            {{-- Spacer --}}
            <div class="mt-auto pt-3 border-t border-hairline">
                <div class="flex items-center gap-3 px-2">
                    <div class="w-8 h-8 rounded-full bg-surface-2 grid place-items-center text-ink-2 font-bold text-xs">
                        {{ mb_substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[13px] font-semibold truncate">{{ auth()->user()->name }}</div>
                        <div class="text-[11px] text-muted truncate">{{ auth()->user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-muted hover:text-danger p-1" title="خروج">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Mobile top bar --}}
        <header class="md:hidden fixed inset-x-0 top-0 h-14 bg-surface border-b border-hairline flex items-center justify-between px-4 z-30">
            <div class="flex items-center gap-2">
                <x-brand-mark class="w-8 h-8" />
                <span class="text-sm font-bold">TAQAT</span>
            </div>
            <button @click="mobileNav = !mobileNav" class="p-2 text-ink-2">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
        </header>

        {{-- Mobile nav overlay --}}
        <div x-show="mobileNav" x-transition class="md:hidden fixed inset-0 z-40 bg-surface p-4 pt-16" x-cloak>
            <button @click="mobileNav = false" class="absolute top-4 left-4 p-2"><svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg></button>
            <nav class="flex flex-col gap-1 mt-2" @click="mobileNav = false">
                @include('partials.admin-nav')
            </nav>
        </div>

        {{-- Main --}}
        <main class="flex-1 md:mr-60 pt-14 md:pt-0">
            @if (isset($header))
                <div class="px-6 md:px-10 pt-8">{{ $header }}</div>
            @endif

            <div class="px-4 md:px-10 py-6 md:py-8 max-w-[1360px] mx-auto">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
