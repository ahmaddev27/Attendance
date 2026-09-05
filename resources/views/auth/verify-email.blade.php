<x-guest-layout>
    <div class="mb-6">
        <h2 class="text-xl font-bold text-ink">تأكيد البريد الإلكتروني</h2>
    </div>

    <div class="mb-4 text-sm text-ink-2">
        {{ __('شكراً لتسجيلك! قبل البدء، يرجى تأكيد بريدك الإلكتروني بالضغط على الرابط الذي أرسلناه لك. إذا لم تستلم الرسالة، يسعدنا إرسالها مرة أخرى.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-success bg-success-soft rounded-lg px-3 py-2">
            {{ __('تم إرسال رابط تأكيد جديد إلى البريد الإلكتروني الذي أدخلته عند التسجيل.') }}
        </div>
    @endif

    <div class="flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button>
                {{ __('إعادة إرسال الرابط') }}
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-xs text-ink-2 hover:text-ink hover:underline">
                {{ __('تسجيل الخروج') }}
            </button>
        </form>
    </div>
</x-guest-layout>
