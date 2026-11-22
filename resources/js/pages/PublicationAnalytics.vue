<script setup lang="ts">
import ChannelObservationReport from '@/components/ChannelObservationReport.vue';
import PublicationMetrics from '@/components/PublicationMetrics.vue';
import type { MetricsReport } from '@/types/analytics';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

defineProps<{ analytics: MetricsReport }>();
const route = useRoute();
</script>
<template>
    <AppLayout :breadcrumbs="[{ title: 'Обзор', href: route('dashboard') }]"
        ><Head title="Аналитика публикаций" /><ChannelObservationReport
            v-if="analytics.channelReport"
            :report="analytics"
            :observed="analytics.channelReport" /><PublicationMetrics v-else :report="analytics" detail
    /></AppLayout>
</template>
