<div x-data="{
    toasts: [],
    add(msg, type = 'success', duration = 4000) {
        const id = Date.now() + Math.random();
        this.toasts.push({ id, msg, type });
        setTimeout(() => this.remove(id), duration);
    },
    remove(id) {
        this.toasts = this.toasts.filter(t => t.id !== id);
    }
}"
     x-on:toast.window="add($event.detail.message, $event.detail.type || 'success')"
     x-init="
        @if (session('toast'))
            add(@js(session('toast.message')), @js(session('toast.type') ?? 'success'));
        @endif
     "
     class="fixed top-4 left-4 z-[100] flex flex-col gap-2 pointer-events-none"
     style="direction: rtl;">
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-x-4"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 -translate-x-4"
            @click="remove(toast.id)"
            class="pointer-events-auto flex items-start gap-3 min-w-[280px] max-w-[380px] bg-surface border rounded-xl shadow-lg p-3.5 cursor-pointer"
            :class="{
                'border-success/40': toast.type === 'success',
                'border-danger/40': toast.type === 'error' || toast.type === 'danger',
                'border-warn/40': toast.type === 'warn' || toast.type === 'warning',
                'border-brand/40': toast.type === 'info'
            }">
            <div class="flex-shrink-0 w-8 h-8 rounded-lg grid place-items-center"
                 :class="{
                    'bg-success-soft text-success': toast.type === 'success',
                    'bg-danger-soft text-danger': toast.type === 'error' || toast.type === 'danger',
                    'bg-warn-soft text-warn': toast.type === 'warn' || toast.type === 'warning',
                    'bg-brand-soft text-brand-ink': toast.type === 'info'
                 }">
                <template x-if="toast.type === 'success'">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12l5 5L20 7"/></svg>
                </template>
                <template x-if="toast.type === 'error' || toast.type === 'danger'">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </template>
                <template x-if="toast.type === 'warn' || toast.type === 'warning'">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 9v4M12 17h.01M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/></svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                </template>
            </div>
            <div class="flex-1 min-w-0 pt-0.5">
                <div class="text-sm font-semibold text-ink leading-snug" x-text="toast.msg"></div>
            </div>
            <button @click.stop="remove(toast.id)" class="flex-shrink-0 text-muted hover:text-ink p-0.5">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
</div>
