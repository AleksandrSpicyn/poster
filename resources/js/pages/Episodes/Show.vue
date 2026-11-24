<script setup lang="ts">
import EpisodeWorkspace from '@/components/episodes/EpisodeWorkspace.vue';
import type { EpisodeWorkspaceProps } from '@/types/episodes';

const props = defineProps<EpisodeWorkspaceProps>();
</script>

<template>
    <EpisodeWorkspace :key="props.episode.id" v-bind="props" />
</template>
