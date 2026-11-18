<script setup>
import { computed } from 'vue';
import { fieldClasses, primaryButtonClasses, secondaryButtonClasses } from '../ui';

const props = defineProps({
    modelValue: { type: Object, required: true },
    branches: { type: Array, required: true },
    propertyTypes: { type: Array, required: true },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'submit', 'reset']);

// Branches share regions, so de-duplicate for the area filter.
const regions = computed(() =>
    [...new Set(props.branches.map((branch) => branch.region))].sort(),
);

const hasFilters = computed(() => Object.values(props.modelValue).some(Boolean));

function update(key, value) {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
}
</script>

<template>
    <form
        class="mb-6 flex flex-wrap items-end gap-3"
        @submit.prevent="emit('submit')"
    >
        <div class="flex flex-col gap-1">
            <label for="property_type" class="text-xs font-medium text-slate-600">Type</label>
            <select
                id="property_type"
                :value="modelValue.property_type"
                :class="fieldClasses"
                @change="update('property_type', $event.target.value)"
            >
                <option value="">Any type</option>
                <option v-for="type in propertyTypes" :key="type.value" :value="type.value">
                    {{ type.label }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="region" class="text-xs font-medium text-slate-600">Area</label>
            <select
                id="region"
                :value="modelValue.region"
                :class="fieldClasses"
                @change="update('region', $event.target.value)"
            >
                <option value="">Any area</option>
                <option v-for="region in regions" :key="region" :value="region">
                    {{ region }}
                </option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="min_bedrooms" class="text-xs font-medium text-slate-600">Min beds</label>
            <input
                id="min_bedrooms"
                :value="modelValue.min_bedrooms"
                type="number"
                min="0"
                max="20"
                placeholder="Any"
                :class="[fieldClasses, 'w-28']"
                @input="update('min_bedrooms', $event.target.value)"
            />
        </div>

        <div class="flex flex-col gap-1">
            <label for="max_price" class="text-xs font-medium text-slate-600">Max price (£)</label>
            <input
                id="max_price"
                :value="modelValue.max_price"
                type="number"
                min="0"
                step="10000"
                placeholder="Any"
                :class="[fieldClasses, 'w-36']"
                @input="update('max_price', $event.target.value)"
            />
        </div>

        <button
            type="submit"
            :disabled="processing"
            :class="primaryButtonClasses"
        >
            Search
        </button>

        <button
            v-if="hasFilters"
            type="button"
            :class="secondaryButtonClasses"
            @click="emit('reset')"
        >
            Clear
        </button>
    </form>
</template>
