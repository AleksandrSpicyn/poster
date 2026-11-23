<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { amount } from '@/types/analytics';
defineProps<{ items: { id?: string; label: string; value: number | null; unit?: string; note?: string; description?: string; href?: string }[] }>();
</script>
<template>
    <div class="publication-counters">
        <component
            :is="item.href ? Link : 'div'"
            v-for="item in items"
            :id="item.id"
            :key="item.label"
            :href="item.href"
            class="metric-tile"
            :title="item.description"
        >
            <span>{{ item.label }}</span><strong
                ><span v-if="item.value === null" class="sr-only">Нет данных</span
                ><span :aria-hidden="item.value === null || undefined"
                    >{{ amount(item.value) }}{{ item.value !== null && item.unit ? ' ' + item.unit : '' }}</span
                ></strong
            >
            <small v-if="item.note">{{ item.note }}</small>
        </component>
    </div>
</template>
<style scoped>
.publication-counters {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}
.metric-tile {
    display: grid;
    min-width: 0;
    overflow-wrap: anywhere;
    align-content: start;
    gap: 6px;
    min-height: 88px;
    padding: 10px 13px;
    border: 1px solid var(--border);
    background: var(--card);
    border-radius: 12px;
}
.metric-tile > span,
.metric-tile small {
    font-size: 14px;
    line-height: 1.4;
    color: var(--muted-foreground);
}
.metric-tile strong {
    font-size: 24px;
    letter-spacing: -0.02em;
    line-height: 1.4;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}
a.metric-tile:hover {
    border-color: var(--primary);
}
@media (max-width: 1099px) {
    .publication-counters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
</style>
