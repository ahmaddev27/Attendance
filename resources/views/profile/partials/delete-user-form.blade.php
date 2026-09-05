<section x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
    <header class="mb-4">
        <h2 class="text-lg font-bold text-danger">حذف الحساب</h2>
        <p class="mt-1 text-xs text-muted">
            سيتم حذف الحساب وكل بياناته نهائياً. لا يمكن التراجع عن هذه العملية.
        </p>
    </header>

    <button @click="open = true" type="button"
            class="inline-flex items-center px-4 py-2 bg-danger hover:bg-danger text-white rounded-lg text-sm font-semibold shadow transition">
        حذف الحساب
    </button>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
         @click.self="open = false"
         @keydown.escape.window="open = false"
         role="dialog"
         aria-modal="true">
        <div x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="bg-surface rounded-2xl w-full max-w-md p-6 shadow-lg">
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <h3 class="text-lg font-bold text-ink mb-2">تأكيد حذف الحساب</h3>
                <p class="text-sm text-muted mb-4">
                    أدخل كلمة المرور لتأكيد الحذف النهائي.
                </p>

                <div>
                    <x-input-label for="password" value="كلمة المرور" class="sr-only" />
                    <x-text-input id="password" name="password" type="password" placeholder="كلمة المرور" dir="ltr" style="text-align:right" />
                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1.5" />
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button @click="open = false" type="button"
                            class="inline-flex items-center px-4 py-2 bg-transparent border border-hairline-strong text-ink-2 hover:bg-surface-2 rounded-lg text-sm font-semibold transition">
                        إلغاء
                    </button>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-danger hover:bg-danger text-white rounded-lg text-sm font-semibold shadow transition">
                        حذف الحساب
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
