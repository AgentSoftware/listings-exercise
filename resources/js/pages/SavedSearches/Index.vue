<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import ListingFilters from '../../components/ListingFilters.vue';
import Pagination from '../../components/Pagination.vue';
import { useListingSearch } from '../../composables/useListingSearch';

const props = defineProps({
    listings: { type: Object, required: true },
    branches: { type: Array, required: true },
    propertyTypes: { type: Array, required: true },
    filters: { type: Object, required: true },
    savedSearches: { type: Array, required: true },
});

const { form, processing, activeFilters, search, reset } = useListingSearch(
    '/saved-searches',
    props.filters,
);

const saving = ref(false);
const deletingId = ref(null);

function saveSearch() {
    router.post('/saved-searches', activeFilters(), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
    });
}

function selectSavedSearch(saved) {
    form.value = {
        property_type: saved.property_type ?? '',
        region: saved.region ?? '',
        min_bedrooms: saved.min_bedrooms ?? '',
        max_price: saved.max_price ?? '',
    };
    search();
}

function deleteSearch(id) {
    router.delete(`/saved-searches/${id}`, {
        preserveScroll: true,
        onStart: () => (deletingId.value = id),
        onFinish: () => (deletingId.value = null),
    });
}

function describeSavedSearch(saved) {
    const parts = [];
    if (saved.property_type) parts.push(saved.property_type);
    if (saved.min_bedrooms) parts.push(`${saved.min_bedrooms}+ beds`);
    if (saved.max_price) parts.push(`up to £${saved.max_price.toLocaleString()}`);
    if (saved.region) parts.push(saved.region);
    return parts.length ? parts.join(' · ') : 'Any listing';
}
</script>

<template>
    <Head title="Saved searches" />

    <AppLayout heading="Saved searches" subheading="Select a saved search, or search for more.">
        <div
            v-if="savedSearches.length"
            class="mb-6 divide-y divide-slate-200 rounded-xl border border-slate-200"
        >
            <div
                v-for="saved in savedSearches"
                :key="saved.id"
                class="flex items-center justify-between px-4 py-3 transition hover:bg-slate-50"
            >
                <button
                    type="button"
                    class="flex-1 text-left text-sm text-slate-700"
                    @click="selectSavedSearch(saved)"
                >
                    {{ describeSavedSearch(saved) }}
                </button>
                <button
                    type="button"
                    :disabled="deletingId === saved.id"
                    aria-label="Delete saved search"
                    class="ml-3 text-slate-400 transition hover:text-red-600 disabled:opacity-50"
                    @click.stop="deleteSearch(saved.id)"
                >
                    ✕
                </button>
            </div>
        </div>
        <p v-else class="mb-6 text-sm text-slate-500">
            You haven't saved any searches yet — save one below.
        </p>

        <ListingFilters
            v-model="form"
            :branches="branches"
            :property-types="propertyTypes"
            :processing="processing"
            @submit="search"
            @reset="reset"
        />

        <div class="mb-4 flex items-center justify-between">
            <p class="text-sm text-slate-500" aria-live="polite">
                <span v-if="processing">Loading…</span>
                <span v-else>{{ listings.meta.total }} listing(s) found</span>
            </p>

            <button
                type="button"
                :disabled="saving"
                class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                @click="saveSearch"
            >
                {{ saving ? 'Saving…' : 'Save this search' }}
            </button>
        </div>

        <div
            v-if="listings.data.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
            :class="processing && 'opacity-50 transition'"
        >
            <ListingCard
                v-for="listing in listings.data"
                :key="listing.id"
                :listing="listing"
            />
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No listings match those filters.
        </p>

        <Pagination :meta="listings.meta" :links="listings.links" />
    </AppLayout>
</template>
