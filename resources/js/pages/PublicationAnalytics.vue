<script setup lang="ts">
import ChannelObservationReport from '@/components/ChannelObservationReport.vue';
import PublicationMetrics from '@/components/PublicationMetrics.vue';
import type { MetricsReport } from '@/types/analytics';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';

defineProps<{ analytics: MetricsReport }>();
const route = useRoute();
const page = usePage();
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Обзор', href: route('dashboard') },
            { title: 'Аналитика публикаций', href: route('dashboard.analytics') },
            ...(page.url.includes('view=') || analytics.channelReport ? [{ title: 'Отчёт', href: page.url }] : []),
        ]"
        ><Head title="Аналитика публикаций" /><ChannelObservationReport
            v-if="analytics.channelReport"
            :report="analytics"
            :observed="analytics.channelReport" /><PublicationMetrics v-else :report="analytics" detail
    /></AppLayout>
</template>
