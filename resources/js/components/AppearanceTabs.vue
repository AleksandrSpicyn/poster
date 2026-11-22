<script setup lang="ts">
import type { Appearance } from '@/types';
defineProps<{ appearance: Appearance }>();
defineEmits<{ select: [value: Appearance] }>();
const choices = [
    { value: 'light', label: 'Светлая' },
    { value: 'dark', label: 'Тёмная' },
    { value: 'system', label: 'Системная' },
] as const;
</script>

<template>
    <div class="theme-options" role="group" aria-label="Тема интерфейса">
        <button
            v-for="choice in choices"
            :key="choice.value"
            type="button"
            :aria-pressed="appearance === choice.value"
            @click="$emit('select', choice.value)"
        >
            <span :class="['theme-preview', choice.value]" aria-hidden="true"><i></i><i></i><i></i></span>
            {{ choice.label }} <span v-if="appearance === choice.value" aria-hidden="true">✓</span>
        </button>
    </div>
</template>
<style scoped>
.theme-options {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
button {
    text-align: left;
    min-width: 0;
    padding: 12px 14px 27px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    font-weight: 700;
}
button[aria-pressed='true'] {
    border-color: var(--ring);
}
button:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 3px;
}
.theme-preview {
    --preview-bg: var(--background);
    --preview-nav: var(--sidebar);
    --preview-row: var(--secondary);
    background: var(--preview-bg);
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr);
    grid-template-rows: repeat(3, 18px);
    gap: 10px 12px;
    height: 122px;
    padding: 14px 24px 0 5px;
    margin-bottom: 16px;
    border-radius: 8px;
}
.theme-preview.light {
    --preview-bg: #f6f6ef;
    --preview-nav: #e7e9e4;
    --preview-row: #e6eae1;
}
.theme-preview.dark {
    --preview-bg: #17191b;
    --preview-nav: #121415;
    --preview-row: #292d30;
}
.theme-preview::before {
    content: '';
    grid-row: span 3;
    height: 109px;
    margin-top: -9px;
    background: var(--preview-nav);
    border-radius: 4px;
}
.theme-preview i {
    border-radius: 3px;
    background: var(--preview-row);
}
.theme-preview i:nth-of-type(2) {
    margin-inline-end: 8px;
}
.theme-preview i:nth-of-type(3) {
    margin-inline-end: 16px;
}
@media (min-width: 768px) and (max-width: 1099px) {
    .theme-options {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 767px) {
    .theme-options {
        grid-template-columns: 1fr;
    }
}
</style>
