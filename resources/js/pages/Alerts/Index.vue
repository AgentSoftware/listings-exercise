<script setup>
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../components/AppLayout.vue';
import ListingCard from '../../components/ListingCard.vue';
import Pagination from '../../components/Pagination.vue';
import { formatDate, formatPrice } from '../../format';

defineProps({
    alerts: { type: Object, required: true },
});

const page = usePage();

const unreadCount = computed(() => page.props.alerts.unread_count);

function markAllRead() {
    router.post('/alerts/read', {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Your alerts" />

    <AppLayout
        heading="Your alerts"
        subheading="New listings that matched one of your saved searches."
    >
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
                {{ alerts.meta.total }} alert(s), {{ unreadCount }} unread
            </p>

            <button
                v-if="unreadCount > 0"
                type="button"
                class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100"
                @click="markAllRead"
            >
                Mark all as read
            </button>
        </div>

        <div v-if="alerts.data.length" class="grid gap-4">
            <article
                v-for="alert in alerts.data"
                :key="alert.id"
                class="rounded-xl"
                :class="!alert.read_at && 'ring-2 ring-slate-900/10'"
            >
                <p class="mb-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span
                        v-if="!alert.read_at"
                        class="rounded-full bg-slate-900 px-2 py-0.5 font-medium text-white"
                    >
                        New
                    </span>
                    <span>{{ formatDate(alert.created_at) }}</span>
                    <span aria-hidden="true">·</span>
                    <span v-if="alert.saved_search_label">
                        Matched “{{ alert.saved_search_label }}”
                    </span>
                    <span v-else class="italic">From a search you've since deleted</span>
                </p>

                <!--
                    A property can be sold or withdrawn after we alerted on it.
                    The detail page only serves live listings, so don't offer a
                    link that would 404 — say what happened instead.
                -->
                <ListingCard v-if="alert.listing.status === 'live'" :listing="alert.listing" />

                <div v-else class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-slate-500">
                    <span class="text-xl font-bold">{{ formatPrice(alert.listing.price) }}</span>
                    <span class="mt-1 block">
                        {{ alert.listing.address_line_1 }}, {{ alert.listing.city }}
                    </span>
                    <span class="mt-3 block text-xs">No longer on the market.</span>
                </div>
            </article>
        </div>

        <p
            v-else
            class="rounded-xl border border-dashed border-slate-300 p-10 text-center text-slate-500"
        >
            No alerts yet. Alerts appear here when a new listing matches one of your
            <Link href="/saved-searches" class="underline underline-offset-2">saved searches</Link>.
        </p>

        <Pagination :meta="alerts.meta" :links="alerts.links" />
    </AppLayout>
</template>
