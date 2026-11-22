<script setup lang="ts">
import { RefreshCw } from '@lucide/vue';
defineProps<{ connected: boolean; enabled: boolean }>();
defineEmits<{ refresh: [] }>();
</script>
<template>
    <button
        type="button"
        class="live-status"
        aria-label="Обновить состояние студии"
        :title="connected ? 'Связь активна' : 'Обновление каждые 30 секунд'"
        @click="$emit('refresh')"
    >
        <i :class="connected ? 'online' : ''" aria-hidden="true" />
        <span>{{ connected ? 'Связь активна' : enabled ? 'Восстанавливаем связь' : 'Обновление каждые 30 с' }}</span>
        <RefreshCw class="size-3.5 shrink-0" aria-hidden="true" />
    </button>
</template>
<style scoped>
.live-status {
    display: flex;
    align-items: center;
    gap: 10px;
    min-height: 60px;
    width: 100%;
    border-top: 1px solid var(--border);
    padding: 8px 12px;
    color: var(--muted-foreground);
    font-size: 13px;
    text-align: left;
}
.live-status i {
    width: 9px;
    height: 9px;
    border-radius: 50%;
    flex: none;
    background: var(--muted-foreground);
}
.live-status i.online {
    background: var(--ring);
}
:global([data-state='collapsed'][data-slot='sidebar'] .live-status span),
:global([data-state='collapsed'][data-slot='sidebar'] .live-status svg) {
    display: none;
}
</style>
