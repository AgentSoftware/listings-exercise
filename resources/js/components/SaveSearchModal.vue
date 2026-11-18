<script setup>
import { computed, nextTick, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { fieldClasses, focusRingClasses, primaryButtonClasses, secondaryButtonClasses } from '../ui';

const props = defineProps({
    // The submitted search (ListingCriteriaResource): what will be saved.
    criteria: { type: Object, required: true },
    alertFrequencies: { type: Array, required: true },
});

const emit = defineEmits(['closed']);

const dialog = ref(null);
const nameInput = ref(null);

// The save modal pre-selects Instantly. (The database default is Never, so
// anything saved without an explicit choice never emails.)
const DEFAULT_FREQUENCY = 'instantly';

const form = useForm({ name: '', alert_frequency: DEFAULT_FREQUENCY });

// Errors about the criteria themselves ("at least one filter", duplicate, or
// a bad value from the URL), shown under the summary.
const criteriaErrors = computed(() =>
    Object.entries(form.errors)
        .filter(([field]) => !['name', 'alert_frequency'].includes(field))
        .map(([, message]) => message),
);

function open() {
    // Defaults are taken from the current props on every open, so reopening
    // (or opening after a new search) always starts fresh.
    form.defaults({ name: props.criteria.default_name, alert_frequency: DEFAULT_FREQUENCY });
    form.reset();
    form.clearErrors();

    // showModal(), not show(): the page behind is inert, and the browser
    // provides focus trapping, Esc and top-layer stacking.
    dialog.value.showModal();
    nextTick(() => nameInput.value?.focus());
}

// Every way out (×, Cancel, Esc, backdrop) closes without saving.
function close() {
    dialog.value?.close();
}

// Fires however the dialog closed, including after a successful save.
function onClose() {
    form.clearErrors();
    emit('closed');
}

// Clicks inside the panel land on its children. Only a click on the backdrop
// targets the <dialog> element itself.
function onDialogClick(event) {
    if (event.target === dialog.value) {
        close();
    }
}

function submit() {
    form
        // The URL's criteria plus the name and frequency.
        .transform((data) => ({ ...props.criteria.query, ...data }))
        .post('/saved-searches', {
            preserveScroll: true,
            // Keep the modal mounted, so errors show inline.
            preserveState: true,
            onSuccess: () => close(),
        });
}

defineExpose({ open });
</script>

<template>
    <dialog
        ref="dialog"
        aria-labelledby="save-search-title"
        class="m-auto w-full max-w-md rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-slate-900/50"
        @cancel.prevent="close"
        @close="onClose"
        @click="onDialogClick"
    >
        <form class="p-6" @submit.prevent="submit">
            <div class="flex items-start justify-between gap-4">
                <h2 id="save-search-title" class="text-lg font-bold">Save this search</h2>

                <button
                    type="button"
                    aria-label="Close"
                    :class="['-m-1 rounded-lg p-1 text-slate-400 transition hover:text-slate-900', focusRingClasses]"
                    @click="close"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" d="M5 5l10 10M15 5L5 15" />
                    </svg>
                </button>
            </div>

            <p class="mt-2 text-sm text-slate-600">{{ criteria.summary }}</p>
            <p v-for="error in criteriaErrors" :key="error" class="mt-2 text-sm text-red-600">{{ error }}</p>

            <div class="mt-5 flex flex-col gap-1">
                <label for="save-search-name" class="text-xs font-medium text-slate-600">Name</label>
                <input
                    id="save-search-name"
                    ref="nameInput"
                    v-model="form.name"
                    type="text"
                    maxlength="100"
                    :class="fieldClasses"
                    :aria-invalid="Boolean(form.errors.name)"
                    aria-describedby="save-search-name-error"
                />
                <p v-if="form.errors.name" id="save-search-name-error" class="text-sm text-red-600">
                    {{ form.errors.name }}
                </p>
            </div>

            <div class="mt-4 flex flex-col gap-1">
                <label for="save-search-frequency" class="text-xs font-medium text-slate-600">Email me</label>
                <select
                    id="save-search-frequency"
                    v-model="form.alert_frequency"
                    :class="fieldClasses"
                    :aria-invalid="Boolean(form.errors.alert_frequency)"
                >
                    <option v-for="frequency in alertFrequencies" :key="frequency.value" :value="frequency.value">
                        {{ frequency.label }}
                    </option>
                </select>
                <p v-if="form.errors.alert_frequency" class="text-sm text-red-600">{{ form.errors.alert_frequency }}</p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button type="button" :class="secondaryButtonClasses" @click="close">Cancel</button>
                <button type="submit" :disabled="form.processing" :class="primaryButtonClasses">Save search</button>
            </div>
        </form>
    </dialog>
</template>
