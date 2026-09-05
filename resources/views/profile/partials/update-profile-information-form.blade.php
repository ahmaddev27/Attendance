<section>
    <header class="mb-4">
        <h2 class="text-lg font-bold text-ink">معلومات الحساب</h2>
        <p class="mt-1 text-xs text-muted">حدّث اسمك وبريدك الإلكتروني</p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">@csrf</form>

    <form method="post" action="{{ route('profile.update') }}" class="space-y-4">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('الاسم')" />
            <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('البريد الإلكتروني')" />
            <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" dir="ltr" style="text-align:right" />
            <x-input-error class="mt-1.5" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2 text-xs text-warn">
                    بريدك الإلكتروني غير مؤكد.
                    <button form="send-verification" class="underline text-brand hover:text-brand-hover mx-1">
                        إعادة إرسال رابط التأكيد
                    </button>
                </div>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-xs text-success">تم إرسال رابط تأكيد جديد إلى بريدك.</p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-3 pt-2">
            <x-primary-button class="!w-auto">{{ __('حفظ') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
                   class="text-xs text-success font-semibold">تم الحفظ.</p>
            @endif
        </div>
    </form>
</section>
