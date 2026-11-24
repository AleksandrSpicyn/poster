<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { Timer } from '@lucide/vue';

const page = usePage<SharedData>();
const seconds = new Intl.NumberFormat('ru-RU');
</script>

<template>
    <span v-if="page.props.balance" class="inline-flex text-xs leading-tight" aria-live="polite" aria-atomic="true">
        <span class="text-muted-foreground">Баланс</span>
        <span class="text-foreground inline-flex items-center gap-1 font-semibold whitespace-nowrap tabular-nums">
            <Timer class="text-muted-foreground size-3 shrink-0 -translate-y-[0.125em]" aria-hidden="true" />{{
                seconds.format(page.props.balance.available)
            }}
            с
        </span>
    </span>
</template>
