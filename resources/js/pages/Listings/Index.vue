<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import ListingFilters from '../../components/ListingFilters.vue';
import Pagination from '../../components/Pagination.vue';

const props = defineProps({
    listings: { type: Object, required: true },
    branches: { type: Array, required: true },
    propertyTypes: { type: Array, required: true },
    filters: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const processing = ref(false);

// Seeded from the server so the filter form survives a refresh, a shared link
// or the back button — the query string is the single source of truth.
const form = ref({
    property_type: props.filters.property_type ?? '',
    region: props.filters.region ?? '',
    min_bedrooms: props.filters.min_bedrooms ?? '',
    max_price: props.filters.max_price ?? '',
});

// Blank fields are dropped rather than sent empty, so the URL stays clean — and
// so a saved search records only the criteria the user actually chose.
const activeFilters = computed(() =>
    Object.fromEntries(
        Object.entries(form.value).filter(([, value]) => value !== '' && value !== null),
    ),
);

const hasFilters = computed(() => Object.keys(activeFilters.value).length > 0);

function search() {
    router.get('/', activeFilters.value, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        // Inertia cancels the in-flight visit when a new one starts, so rapid
        // searches can't land out of order.
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}

function reset() {
    form.value = { property_type: '', region: '', min_bedrooms: '', max_price: '' };
    search();
}

// Saves the filters as they are on screen, which is also what the URL shows.
function saveSearch() {
    router.post('/saved-searches', activeFilters.value, { preserveScroll: true });
}
</script>

<template>
    <Head title="Properties for sale" />

    <AppLayout heading="Properties for sale" subheading="Across our branches.">
        <ListingFilters
            v-model="form"
            :branches="branches"
            :property-types="propertyTypes"
            :processing="processing"
            @submit="search"
            @reset="reset"
        />

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500" aria-live="polite">
                <span v-if="processing">Loading…</span>
                <span v-else>{{ listings.meta.total }} listing(s) found</span>
            </p>

            <button
                v-if="hasFilters"
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                @click="saveSearch"
            >
                Save this search
            </button>
        </div>

        <p v-if="errors.criteria" class="mb-4 text-sm text-red-600">{{ errors.criteria }}</p>

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
