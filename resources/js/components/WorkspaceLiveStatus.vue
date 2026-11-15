<script setup lang="ts">
import { useWorkspaceRealtime } from '@/composables/useWorkspaceRealtime';
import { Radio, RefreshCw } from '@lucide/vue';

const { connected, enabled, refresh } = useWorkspaceRealtime();
</script>

<template>
    <div class="text-muted-foreground flex items-center justify-end gap-2 px-4 pt-3 text-xs sm:px-6">
        <Radio class="size-3.5" :class="connected ? 'text-emerald-600 dark:text-emerald-400' : ''" aria-hidden="true" />
        <span>{{ connected ? 'Live updates' : enabled ? 'Reconnecting · updates every 30s' : 'Updates every 30s' }}</span>
        <button
            type="button"
            class="hover:text-foreground focus-visible:ring-ring/50 inline-flex min-h-11 min-w-11 items-center justify-center rounded-md outline-none focus-visible:ring-2"
            aria-label="Refresh current statuses"
            @click="refresh"
        >
            <RefreshCw class="size-3.5" aria-hidden="true" />
        </button>
    </div>
</template>
