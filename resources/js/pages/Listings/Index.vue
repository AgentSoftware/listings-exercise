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
});

const { form, processing, activeFilters, search, reset } = useListingSearch('/', props.filters);

const saving = ref(false);

function saveSearch() {
    router.post('/saved-searches', activeFilters(), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (saving.value = true),
        onFinish: () => (saving.value = false),
    });
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
