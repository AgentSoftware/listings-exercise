<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import { secondaryButtonClasses } from '../../ui';

defineProps({
    savedSearches: { type: Array, required: true },
});

// The same destroy route and policy as the listings page's "✓ Saved" button.
// The server redirects back with a flash message.
function destroy(savedSearch) {
    router.delete(`/saved-searches/${savedSearch.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout heading="Saved searches" subheading="Re-run a search, or get alerted when something new matches.">
        <ul v-if="savedSearches.length" class="space-y-3">
            <li
                v-for="savedSearch in savedSearches"
                :key="savedSearch.id"
                class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4"
            >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-bold">{{ savedSearch.name }}</h2>

                        <span
                            v-if="savedSearch.alerts_enabled"
                            class="rounded-full bg-slate-900 px-2.5 py-0.5 text-xs font-medium text-white"
                        >
                            Alerts: {{ savedSearch.alert_frequency_label }}
                        </span>
                        <span
                            v-else
                            class="rounded-full border border-slate-200 px-2.5 py-0.5 text-xs text-slate-400"
                        >
                            Alerts off
                        </span>
                    </div>

                    <p class="mt-1 text-sm text-slate-600">{{ savedSearch.summary }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <Link
                        :href="savedSearch.results_url"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm hover:bg-slate-100"
                    >
                        View results
                    </Link>
                    <button type="button" :class="secondaryButtonClasses" @click="destroy(savedSearch)">
                        Delete
                    </button>
                </div>
            </li>
        </ul>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No saved searches yet. Search for properties, then choose “Save search”.
        </p>
    </AppLayout>
</template>
