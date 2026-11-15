<script setup lang="ts">
import type { PromptSummary } from '@/types/content';
import { CheckCircle2, Clock3 } from '@lucide/vue';

defineProps<{ prompt: PromptSummary }>();
</script>

<template>
    <span
        class="inline-flex max-w-full items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium"
        :class="prompt.generated ? 'border-primary/20 bg-primary/10 text-primary' : 'border-border bg-muted text-muted-foreground'"
    >
        <component :is="prompt.generated ? CheckCircle2 : Clock3" class="size-3.5 shrink-0" aria-hidden="true" />
        {{ prompt.stage_label }}
    </span>
</template>
