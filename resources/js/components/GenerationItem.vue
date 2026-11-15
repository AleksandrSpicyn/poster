<script setup lang="ts">
import GenerationStage from '@/components/GenerationStage.vue';
import VideoPreview from '@/components/VideoPreview.vue';
import type { PromptSummary } from '@/types/content';

defineProps<{ prompt: PromptSummary }>();
</script>

<template>
    <article class="space-y-3 p-4 sm:p-5">
        <div class="min-w-0">
            <p class="line-clamp-2 text-sm leading-relaxed font-medium break-words">{{ prompt.title }}</p>
            <p class="text-muted-foreground mt-1.5 text-xs">{{ prompt.project ?? 'Deleted project' }} · {{ prompt.created_at ?? '—' }}</p>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3"><GenerationStage :prompt="prompt" /><VideoPreview :prompt="prompt" /></div>
    </article>
</template>
