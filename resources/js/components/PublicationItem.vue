<script setup lang="ts">
import ChannelIcon from '@/components/ChannelIcon.vue';
import type { PostSummary } from '@/types/content';
import { ArrowUpRight } from '@lucide/vue';

defineProps<{ post: PostSummary }>();
</script>

<template>
    <article class="flex items-center justify-between gap-4 p-4 sm:p-5">
        <div class="min-w-0">
            <p class="flex items-center gap-2 text-sm font-medium capitalize">
                <ChannelIcon :channel="post.channel" />{{ post.channel ?? 'Канал удалён' }}
            </p>
            <p class="text-muted-foreground mt-1 text-xs">Публикация №{{ post.id }} · {{ post.created_at ?? '—' }}</p>
        </div>
        <a
            v-if="post.url"
            :href="post.url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary hover:bg-accent inline-flex size-11 shrink-0 items-center justify-center rounded-lg border"
            :aria-label="`Открыть публикацию ${post.id} (в новой вкладке)`"
            ><ArrowUpRight class="size-4" /></a
        ><span v-else class="text-muted-foreground text-xs">Ссылки пока нет</span>
    </article>
</template>
