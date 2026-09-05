<x-app-layout>
    <div>
        <div class="pb-6 mb-6 border-b border-hairline">
            <div class="text-xs text-muted mb-1">الحساب</div>
            <h1 class="text-2xl md:text-3xl font-bold text-ink tracking-tight">الملف الشخصي</h1>
        </div>

        <div class="max-w-2xl space-y-6">
            <div class="bg-surface border border-hairline rounded-xl p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="bg-surface border border-hairline rounded-xl p-6">
                @include('profile.partials.update-password-form')
            </div>

            <div class="bg-surface border border-danger-soft rounded-xl p-6">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
