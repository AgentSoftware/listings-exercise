<script>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// Module scope: one listener for the app's lifetime, so a page component that
// remounts during a visit can't miss the event.
//
// It listens to Inertia's `success` event (a real server response), not to
// prop changes: Back/Forward restores a page from history without a response,
// and must not replay an old "Search saved". Every message gets a fresh id, so
// the same text firing twice in a row (save, delete, save) shows again.
const current = ref(null);
let nextId = 0;
let timer = null;

router.on('success', (event) => {
    const message = event.detail.page.props.flash?.message;

    if (!message) {
        return;
    }

    current.value = { id: ++nextId, text: message };

    clearTimeout(timer);
    timer = setTimeout(() => (current.value = null), 3000);
});

// Rendered once, by AppLayout. The overlay never blocks the page: no backdrop,
// and pointer-events-none so clicks pass through.
export default {
    setup() {
        return { current };
    },
};
</script>

<template>
    <div
        class="pointer-events-none fixed inset-0 z-50 flex items-center justify-center p-4"
        role="status"
        aria-live="polite"
    >
        <Transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-700"
            leave-to-class="opacity-0"
        >
            <p
                v-if="current"
                :key="current.id"
                class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-medium text-white shadow-lg"
            >
                {{ current.text }}
            </p>
        </Transition>
    </div>
</template>
