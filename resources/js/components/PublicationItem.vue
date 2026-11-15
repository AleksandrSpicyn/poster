<script setup lang="ts">
import type { PostSummary } from '@/types/content';
import { ArrowUpRight } from '@lucide/vue';

defineProps<{ post: PostSummary }>();
</script>

<template>
    <article class="flex items-center justify-between gap-4 p-4 sm:p-5">
        <div class="min-w-0">
            <p class="text-sm font-medium capitalize">{{ post.channel ?? 'Deleted channel' }}</p>
            <p class="text-muted-foreground mt-1 text-xs">Post #{{ post.id }} · {{ post.created_at ?? '—' }}</p>
        </div>
        <a
            v-if="post.url"
            :href="post.url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary hover:bg-accent inline-flex size-11 shrink-0 items-center justify-center rounded-lg border"
            :aria-label="`Open post ${post.id} (opens in a new tab)`"
            ><ArrowUpRight class="size-4" /></a
        ><span v-else class="text-muted-foreground text-xs">No link</span>
    </article>
</template>
