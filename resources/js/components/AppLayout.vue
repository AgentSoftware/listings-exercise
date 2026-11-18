<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    heading: { type: String, required: true },
    subheading: { type: String, default: null },
});

const page = usePage();

// Null until the database is seeded (see ActAsDemoUser).
const user = computed(() => page.props.auth.user);

const navLinkClasses = 'text-sm font-medium text-slate-500 transition hover:text-slate-900';
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="mb-8">
            <div class="flex items-center justify-between gap-4">
                <Link href="/" :class="navLinkClasses">Street Listings</Link>

                <nav v-if="user" class="flex items-center gap-4" aria-label="Account">
                    <Link href="/saved-searches" :class="navLinkClasses">Saved searches</Link>
                </nav>
            </div>
            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ heading }}</h1>
            <p v-if="subheading" class="mt-1 text-slate-500">{{ subheading }}</p>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
