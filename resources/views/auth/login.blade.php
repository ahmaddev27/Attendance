<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-ink">أهلاً بعودتك</h2>
        <p class="text-xs text-muted mt-1">أدخل بيانات حسابك للدخول للوحة الأدمن</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('البريد الإلكتروني')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" style="direction:ltr;text-align:right" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" :value="__('كلمة المرور')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" style="direction:ltr;text-align:right" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div class="flex items-center justify-between text-xs">
            <label for="remember_me" class="inline-flex items-center gap-2 text-ink-2">
                <input id="remember_me" type="checkbox" class="rounded border-hairline-strong text-brand focus:ring-brand" name="remember">
                <span>تذكّرني</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-brand hover:underline" href="{{ route('password.request') }}">
                    {{ __('نسيت كلمة المرور؟') }}
                </a>
            @endif
        </div>

        <div class="pt-2">
            <x-primary-button>
                {{ __('دخول') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
