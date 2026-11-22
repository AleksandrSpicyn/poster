<script setup lang="ts">
import type { MetricsReport } from '@/components/PublicationMetrics.vue';
import { Link } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';

defineProps<{ report: MetricsReport }>();
const route = useRoute();
const names = { views: 'Просмотры', likes: 'Лайки', comments: 'Комментарии', shares: 'Репосты' };
const format = new Intl.NumberFormat('ru', { signDisplay: 'exceptZero' });
</script>

<template>
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Прирост показателей за период">
        <Link
            v-for="(name, metric) in names"
            :key="metric"
            :href="route('dashboard.analytics', { ...report.filters, metric })"
            class="studio-panel hover:border-primary min-w-0 focus-visible:ring-2"
        >
            <span class="text-muted-foreground text-sm">{{ name }}</span>
            <strong class="mt-2 block text-2xl tabular-nums md:text-3xl">{{
                report.summaries[metric] === null ? '—' : format.format(report.summaries[metric])
            }}</strong>
            <small v-if="report.summaries[metric] === null" class="text-muted-foreground">Нет базы сравнения</small>
        </Link>
    </section>
</template>
