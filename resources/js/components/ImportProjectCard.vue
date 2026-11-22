<script setup lang="ts">
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import type { ProjectSummary } from '@/types/projects';
defineProps<{ name: string; cover?: ProjectSummary['cover']; connection?: string; count?: number | null }>();
</script>
<template>
    <aside class="studio-panel import-card">
        <ProjectArtwork :name="name" :cover="cover" />
        <h2>{{ name }}</h2>
        <template v-if="connection"
            ><small>Подключение</small>
            <p>{{ connection }}</p></template
        ><template v-if="count != null"
            ><small>В источнике</small>
            <p>Клипов: {{ count }}</p></template
        ><small v-if="!connection && count == null">Клиппинг</small>
    </aside>
</template>
<style scoped>
.import-card {
    padding: 16px;
}
.import-card > div {
    width: 100%;
    height: 108px;
    border-radius: 8px;
}
.import-card h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 10px 0 4px;
}
.import-card small {
    display: block;
    color: var(--muted-foreground);
    margin-top: 8px;
    font-size: 14px;
}
@media (max-width: 1099px) {
    .import-card {
        display: none;
    }
}
</style>
