<script setup lang="ts">
import type { ChannelType } from '@/types';

defineProps<{ channel: ChannelType | null }>();
</script>

<template>
    <span v-if="channel" class="inline-flex size-7 shrink-0 items-center justify-center rounded-md bg-white ring-1 ring-black/5" aria-hidden="true">
        <img :src="`/icons/${channel}.svg`" alt="" class="size-5" />
    </span>
</template>
