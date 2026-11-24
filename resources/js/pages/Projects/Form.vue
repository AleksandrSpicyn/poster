<script setup lang="ts">
import ProjectSettingsEditor from '@/components/projects/ProjectSettingsEditor.vue';
import type { ProjectFormProps } from '@/types/projects';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<ProjectFormProps>();
const page = usePage();
const format = computed(() => new URL(page.url, 'http://poster.local').searchParams.get('format') ?? '');
</script>

<template>
    <ProjectSettingsEditor :key="props.project?.id ?? 'new:' + format" v-bind="props" />
</template>
