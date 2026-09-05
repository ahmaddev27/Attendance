<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-ink">تأكيد كلمة المرور</h2>
        <p class="text-xs text-muted mt-1">هذه منطقة محمية. الرجاء تأكيد كلمة المرور.</p>
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="password" :value="__('كلمة المرور')" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" autofocus style="direction:ltr;text-align:right" />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <div class="pt-2">
            <x-primary-button>
                {{ __('تأكيد') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
