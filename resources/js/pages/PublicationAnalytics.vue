<script setup lang="ts">
import PublicationCounters from '@/components/PublicationCounters.vue';
import PublicationMetrics, { type MetricsReport } from '@/components/PublicationMetrics.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

defineProps<{ analytics: MetricsReport }>();
const route = useRoute();
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Обзор', href: route('dashboard') },
            { title: 'Аналитика', href: route('dashboard.analytics') },
        ]"
    >
        <Head title="Аналитика публикаций" />
        <div class="workspace-page">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-3xl font-bold md:text-[40px]">Аналитика публикаций</h1>
                <Link :href="route('dashboard')" class="underline">Обзор студии</Link>
            </div>
            <PublicationCounters :report="analytics" />
            <PublicationMetrics :report="analytics" detail />
        </div>
    </AppLayout>
</template>
