import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

// Shared by the main listings search and saved searches — both need the same
// "seed from server filters, drop blanks, submit via Inertia" behaviour.
export function useListingSearch(baseUrl, filters) {
    const processing = ref(false);

    const form = ref({
        property_type: filters.property_type ?? '',
        region: filters.region ?? '',
        min_bedrooms: filters.min_bedrooms ?? '',
        max_price: filters.max_price ?? '',
    });

    function activeFilters() {
        // Blank fields are dropped rather than sent empty, so the URL stays clean.
        return Object.fromEntries(
            Object.entries(form.value).filter(([, value]) => value !== '' && value !== null),
        );
    }

    function search() {
        router.get(baseUrl, activeFilters(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (processing.value = true),
            onFinish: () => (processing.value = false),
        });
    }

    function reset() {
        form.value = { property_type: '', region: '', min_bedrooms: '', max_price: '' };
        search();
    }

    return { form, processing, activeFilters, search, reset };
}
