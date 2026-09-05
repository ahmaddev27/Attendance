<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-ink">استعادة كلمة المرور</h2>
        <p class="text-xs text-muted mt-1">أدخل بريدك الإلكتروني وسنرسل لك رابط استعادة.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('البريد الإلكتروني')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus style="direction:ltr;text-align:right" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div class="pt-2">
            <x-primary-button>
                {{ __('إرسال رابط الاستعادة') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
