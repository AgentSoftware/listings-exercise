<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingFilters from '../../components/ListingFilters.vue';

defineProps({
    savedSearches: { type: Array, required: true },
    branches: { type: Array, required: true },
    propertyTypes: { type: Array, required: true },
    errors: { type: Object, default: () => ({}) },
});

const blank = { property_type: '', region: '', min_bedrooms: '', max_price: '' };

const form = ref({ ...blank });
const name = ref('');
const processing = ref(false);

const hasCriteria = computed(() => Object.values(form.value).some(Boolean));

// Same shape the browse page posts: blanks are dropped rather than sent empty.
function criteria() {
    return Object.fromEntries(
        Object.entries(form.value).filter(([, value]) => value !== '' && value !== null),
    );
}

function save() {
    router.post(
        '/saved-searches',
        { ...criteria(), name: name.value || null },
        {
            // On success the server redirects to the browse results for the
            // saved criteria, so there is nothing here to reset. This only
            // matters on a validation failure, where the form stays put.
            preserveScroll: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        },
    );
}

function reset() {
    form.value = { ...blank };
}

function remove(search) {
    if (window.confirm(`Delete “${search.label}”? You'll stop being alerted about it.`)) {
        router.delete(`/saved-searches/${search.id}`, { preserveScroll: true });
    }
}

// Everything already on the market for these criteria — the browse page is the
// answer to "what matches right now", so a saved search doesn't backfill.
function matchesUrl(search) {
    return `/?${new URLSearchParams(search.criteria).toString()}`;
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout
        heading="Saved searches"
        subheading="We'll email you once a day when new listings match."
    >
        <section class="mb-10 rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-4 text-sm font-semibold text-slate-900">Save a new search</h2>

            <ListingFilters
                v-model="form"
                :branches="branches"
                :property-types="propertyTypes"
                :processing="processing || !hasCriteria"
                submit-label="Save search"
                @submit="save"
                @reset="reset"
            />

            <div class="flex flex-col gap-1">
                <label for="name" class="text-xs font-medium text-slate-600">
                    Name (optional)
                </label>
                <input
                    id="name"
                    v-model="name"
                    type="text"
                    maxlength="100"
                    placeholder="e.g. Near Mum"
                    class="w-64 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-slate-900 focus:outline-none focus:ring-1 focus:ring-slate-900"
                />
            </div>

            <p v-if="errors.criteria" class="mt-3 text-sm text-red-600">{{ errors.criteria }}</p>
        </section>

        <div v-if="savedSearches.length" class="grid gap-3">
            <article
                v-for="search in savedSearches"
                :key="search.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4"
            >
                <div>
                    <p class="font-medium text-slate-900">{{ search.label }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ search.alerts_count }} alert(s) so far
                    </p>
                </div>

                <div class="flex items-center gap-4 text-sm">
                    <Link :href="matchesUrl(search)" class="text-slate-500 hover:text-slate-900">
                        View matches
                    </Link>
                    <button
                        type="button"
                        class="text-red-600 underline-offset-2 hover:underline"
                        @click="remove(search)"
                    >
                        Delete
                    </button>
                </div>
            </article>
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No saved searches yet. Save one above, or from the
            <Link href="/" class="underline underline-offset-2">browse page</Link>.
        </p>
    </AppLayout>
</template>
