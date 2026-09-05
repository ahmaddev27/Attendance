@php
    $navItems = [
        ['route' => 'admin.overview', 'label' => 'الرئيسية', 'icon' => 'home', 'active' => request()->routeIs('admin.overview')],
        ['route' => 'admin.employees.index', 'label' => 'الموظفون', 'icon' => 'users', 'active' => request()->routeIs('admin.employees.*')],
        ['route' => 'admin.attendance.index', 'label' => 'الحضور', 'icon' => 'calendar', 'active' => request()->routeIs('admin.attendance.*')],
        ['route' => 'admin.leaves.index', 'label' => 'الإجازات', 'icon' => 'check', 'active' => request()->routeIs('admin.leaves.*')],
        ['route' => 'admin.settings.index', 'label' => 'الإعدادات', 'icon' => 'settings', 'active' => request()->routeIs('admin.settings.*')],
        ['route' => 'admin.sms-logs.index', 'label' => 'سجل SMS', 'icon' => 'message', 'active' => request()->routeIs('admin.sms-logs.*')],
    ];
    $icons = [
        'home' => '<path d="M3 12L12 3l9 9M5 10v10h14V10"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'check' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    ];
@endphp

@foreach($navItems as $item)
    <a href="{{ route($item['route']) }}"
       class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm font-medium transition
              {{ $item['active'] ? 'bg-brand-soft text-brand-ink font-semibold' : 'text-ink-2 hover:bg-surface-2 hover:text-ink' }}">
        <svg class="w-4 h-4 opacity-80 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            {!! $icons[$item['icon']] !!}
        </svg>
        <span>{{ $item['label'] }}</span>
    </a>
@endforeach
