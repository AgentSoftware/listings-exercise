<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SaveSearchModal from './SaveSearchModal.vue';
import { focusRingClasses, outlineButtonClasses } from '../ui';

// Follows the submitted search (the URL), not the filter form: until the user
// clicks Search, the results on screen are still the URL's, so its saved
// state is still accurate.
//
// Every state is computed from props, never copied into a ref. Index.vue
// searches with preserveState, which keeps this component alive between
// visits, so a copied value would go stale.
const props = defineProps({
    criteria: { type: Object, required: true },
    savedSearchId: { type: Number, default: null },
    alertFrequencies: { type: Array, required: true },
});

const page = usePage();

// Hidden when there's no search to save (decided on the server by
// ListingCriteria::isEmpty()), or no user to save it for.
const available = computed(() => !props.criteria.is_empty && Boolean(page.props.auth.user));
const isSaved = computed(() => props.savedSearchId !== null);

const trigger = ref(null);
const modal = ref(null);
// Only request-in-flight state, so a double click can't send two deletes.
const deleting = ref(false);

function onClick() {
    isSaved.value ? destroy() : modal.value.open();
}

// The same destroy route and policy as the saved searches page. There's no
// confirmation step: re-saving is one click, and alerts already raised survive
// because their saved_search_id is set to null. The reloaded props then flip
// the button back to "Save search".
function destroy() {
    router.delete(`/saved-searches/${props.savedSearchId}`, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
    });
}
</script>

<template>
    <template v-if="available">
        <!--
            One element for both states, so focus returning from the modal lands
            on it either way. The Saved label swaps on hover and on keyboard focus
            (touch devices have no hover) using CSS only. The aria-label always
            says what a click does.
        -->
        <button
            ref="trigger"
            type="button"
            :disabled="deleting"
            :aria-label="isSaved ? 'Delete this saved search' : undefined"
            :class="[
                outlineButtonClasses,
                focusRingClasses,
                'group',
                isSaved && 'hover:border-red-300 hover:bg-red-50 hover:text-red-700 focus-visible:border-red-300 focus-visible:bg-red-50 focus-visible:text-red-700 focus-visible:ring-red-600',
            ]"
            @click="onClick"
        >
            <template v-if="isSaved">
                <span class="group-hover:hidden group-focus-visible:hidden">✓ Saved</span>
                <span class="hidden group-hover:inline group-focus-visible:inline">Delete this saved search</span>
            </template>
            <template v-else>Save search</template>
        </button>

        <SaveSearchModal
            ref="modal"
            :criteria="criteria"
            :alert-frequencies="alertFrequencies"
            @closed="trigger?.focus()"
        />
    </template>
</template>
