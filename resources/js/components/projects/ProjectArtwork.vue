<script setup lang="ts">
import type { ProjectSummary } from '@/types/projects';
import { computed } from 'vue';

const props = defineProps<{ name: string; cover?: ProjectSummary['cover'] }>();
const initials = computed(() =>
    props.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toLocaleUpperCase('ru'),
);
</script>

<template>
    <div class="bg-muted text-muted-foreground relative grid place-items-center overflow-hidden" aria-hidden="true">
        <img
            v-if="cover"
            :src="cover.url"
            :style="{ objectPosition: cover.x + '% ' + cover.y + '%' }"
            alt=""
            class="absolute inset-0 h-full w-full object-cover"
            loading="lazy"
        />
        <span v-else class="text-xl font-bold">{{ initials }}</span>
    </div>
</template>
