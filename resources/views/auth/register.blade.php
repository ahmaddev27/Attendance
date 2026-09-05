<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-ink">إنشاء حساب</h2>
        <p class="text-xs text-muted mt-1">أدخل بياناتك لإنشاء حساب جديد</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="name" :value="__('الاسم')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="email" :value="__('البريد الإلكتروني')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" style="direction:ltr;text-align:right" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password" :value="__('كلمة المرور')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" style="direction:ltr;text-align:right" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('تأكيد كلمة المرور')" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" style="direction:ltr;text-align:right" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
        </div>

        <div class="pt-2">
            <x-primary-button>
                {{ __('تسجيل') }}
            </x-primary-button>
        </div>

        <div class="text-center text-xs text-ink-2">
            لديك حساب؟
            <a class="text-brand hover:underline font-semibold" href="{{ route('login') }}">
                {{ __('تسجيل الدخول') }}
            </a>
        </div>
    </form>
</x-guest-layout>
