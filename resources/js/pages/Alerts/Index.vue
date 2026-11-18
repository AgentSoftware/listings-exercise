<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});
</script>

<template>
    <Head title="Alerts" />

    <AppLayout heading="Alerts" subheading="New listings that match your saved searches.">
        <div v-if="alerts.data.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Unread is shown as it was when the page loaded; viewing marks them read. -->
            <div
                v-for="alert in alerts.data"
                :key="alert.id"
                class="flex flex-col gap-2 rounded-xl"
                :class="alert.is_unread && 'ring-2 ring-slate-900 ring-offset-2 ring-offset-slate-50'"
            >
                <div class="flex items-center justify-between gap-2 text-xs text-slate-500">
                    <span class="min-w-0 truncate">
                        {{ alert.search_summary ?? 'From a deleted saved search' }}
                    </span>
                    <span
                        v-if="alert.is_unread"
                        class="shrink-0 rounded-full bg-slate-900 px-2 py-0.5 font-medium text-white"
                    >
                        New
                    </span>
                    <span v-else class="shrink-0">{{ formatDate(alert.created_at) }}</span>
                </div>

                <ListingCard :listing="alert.listing" />
            </div>
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. Save a search with alerts turned on, and new matching listings will appear here.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
