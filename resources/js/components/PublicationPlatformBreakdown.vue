<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useRoute } from '@/composables/useRoute';
import { amount, counterNames, platformNames, type platformShares } from '@/lib/analytics';
import type { AnalyticsFilters, PublicationTimeline } from '@/types/analytics';

const props = defineProps<{
    publications: PublicationTimeline[];
    platforms: ReturnType<typeof platformShares>;
    filters: AnalyticsFilters;
    coverageHref: string;
    detail?: boolean;
}>();
const route = useRoute();
const selectedWatch = computed(() => {
    const rows = props.publications;
    const minutes = rows.map((p) => p.watchMinutesChange);
    return {
        duration: rows.length === 1 ? (rows[0].latest.averageViewDuration ?? null) : null,
        percent: rows.length === 1 ? (rows[0].latest.averageViewPercentage ?? null) : null,
        hours: minutes.length && minutes.every((v): v is number => v != null) ? minutes.reduce((s, v) => s + v, 0) / 60 : null,
    };
});
</script>
<template>
    <section class="studio-panel platforms-panel">
        <h2>
            <Link :href="coverageHref">{{ counterNames[filters.metric] }} по площадкам</Link>
        </h2>
        <Link v-for="row in platforms" :key="row.key" :href="route('dashboard.analytics', { ...filters, platform: row.key })" class="platform-row">
            <span>{{ platformNames[row.key] ?? row.key }}</span><strong>{{ amount(row.value) }}</strong
            ><i :class="'bar-' + row.key" :style="{ '--bar-width': row.pct + '%' }"
        /></Link>
        <p v-if="!platforms.length" class="secondary">Нет измерений за период</p>
        <template v-if="detail">
            <p v-if="selectedWatch.percent != null">Средний процент просмотра: {{ amount(selectedWatch.percent) }} %</p>
            <p v-if="selectedWatch.duration != null">Среднее время просмотра: {{ amount(selectedWatch.duration) }} с</p>
            <p v-if="selectedWatch.hours != null">Прирост времени просмотра: {{ amount(selectedWatch.hours) }} ч</p>
        </template>
    </section>
</template>
<style scoped>
.platform-row {
    display: grid;
    grid-template-columns: 1fr auto;
    align-items: center;
    gap: 6px;
    padding: 11px 0;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
}
.platform-row i {
    grid-column: 1/-1;
    background: var(--muted);
    height: 4px;
}
.platform-row i::before {
    display: block;
    content: '';
    height: 100%;
    width: var(--bar-width);
    background: var(--primary);
}
.platform-row .bar-instagram::before {
    background: var(--progress);
}
.platform-row .bar-tiktok::before {
    background: var(--warning);
}
h2 {
    margin: 0 0 10px;
    font-size: 20px;
    font-weight: 700;
}
.secondary {
    display: block;
    color: var(--muted-foreground);
    font-size: 14px;
}
</style>
