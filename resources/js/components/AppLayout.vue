<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    heading: { type: String, required: true },
    subheading: { type: String, default: null },
});

const page = usePage();

const flash = computed(() => page.props.flash);

const unreadAlerts = computed(() => page.props.alerts?.unread_count ?? 0);

// Matches on the path only, so a filtered browse URL still marks Browse active.
function isCurrent(href) {
    const path = page.url.split('?')[0];

    return href === '/' ? path === '/' : path.startsWith(href);
}

function navClasses(href) {
    return isCurrent(href)
        ? 'font-medium text-slate-900'
        : 'text-slate-500 transition hover:text-slate-900';
}
</script>

<template>
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="mb-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <Link
                    href="/"
                    class="text-sm font-medium text-slate-500 transition hover:text-slate-900"
                >
                    Street Listings
                </Link>

                <nav class="flex items-center gap-5 text-sm">
                    <Link href="/" :class="navClasses('/')">Browse</Link>
                    <Link href="/saved-searches" :class="navClasses('/saved-searches')">
                        Saved searches
                    </Link>
                    <Link href="/alerts" :class="[navClasses('/alerts'), 'flex items-center gap-1.5']">
                        Alerts
                        <span
                            v-if="unreadAlerts"
                            class="rounded-full bg-slate-900 px-1.5 py-0.5 text-xs font-medium text-white"
                        >
                            {{ unreadAlerts }}
                        </span>
                    </Link>
                </nav>
            </div>

            <h1 class="mt-2 text-3xl font-bold tracking-tight">{{ heading }}</h1>
            <p v-if="subheading" class="mt-1 text-slate-500">{{ subheading }}</p>
        </header>

        <p
            v-if="flash"
            class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"
            role="status"
        >
            {{ flash }}
        </p>

        <main>
            <slot />
        </main>
    </div>
</template>
