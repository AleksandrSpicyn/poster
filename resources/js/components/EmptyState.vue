<script setup lang="ts">
import { Inbox } from '@lucide/vue';

defineProps<{ title: string; description: string }>();
</script>

<template>
    <div class="flex flex-col items-center px-6 py-14 text-center">
        <span class="bg-muted text-muted-foreground mb-4 flex size-12 items-center justify-center rounded-2xl"
            ><Inbox class="size-5" aria-hidden="true"
        /></span>
        <h2 class="text-base font-semibold">{{ title }}</h2>
        <p class="text-muted-foreground mt-2 max-w-sm text-sm leading-relaxed">{{ description }}</p>
        <div v-if="$slots.default" class="mt-5"><slot /></div>
    </div>
</template>
