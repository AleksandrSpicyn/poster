<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import { Button } from '@/components/ui/button';
import type { EpisodeAssemblySummary, EpisodeWorkspaceProps } from '@/types/episodes';
import { computed, ref } from 'vue';
const props = defineProps<{ workspace: EpisodeWorkspaceProps; assembly: EpisodeAssemblySummary | null }>();
const expanded = ref(false);
const memory = computed(() => props.workspace.episode.current_revision?.context ?? props.assembly?.continuity_snapshot);
const frame = computed(() => props.assembly?.media.find((item) => item.collection === 'continuation-frame'));
const episodeNumber = (n: number) => String(n).padStart(2, '0');
</script>
<template>
    <div class="memory-screen">
        <Heading title="Сценарий эпизода" description="История" />
        <div class="memory-columns">
            <section class="studio-panel memory-panel">
                <h2>История эпизода</h2>
                <dl>
                    <div class="studio-row">
                        <dt>Источник продолжения</dt>
                        <dd>
                            <template v-if="workspace.predecessorNumber">Эпизод {{ episodeNumber(workspace.predecessorNumber) }} · </template>принятая
                            сборка
                        </dd>
                    </div>
                </dl>
                <div v-if="frame" class="memory-frame">
                    <img :src="frame.url" alt="Последний кадр предыдущего выпуска" /><strong>Последний кадр предыдущего выпуска</strong>
                </div>
                <dl>
                    <div class="studio-row">
                        <dt>Где остановились</dt>
                        <dd>{{ memory?.confirmed_events || 'События не записаны' }}</dd>
                    </div>
                    <div class="studio-row">
                        <dt>Что сохранить</dt>
                        <dd>{{ memory?.character_state || 'Состояние персонажей не записано' }}</dd>
                    </div>
                </dl>
                <Button :aria-expanded="expanded" aria-controls="memory-details" @click="expanded = !expanded">Использовать контекст</Button>
                <div v-show="expanded" id="memory-details" class="memory-details">
                    <h3>Подробности сохранённой памяти</h3>
                    <dl>
                        <div v-if="assembly" class="studio-row">
                            <dt>Версия сборки</dt>
                            <dd>{{ assembly.number }}</dd>
                        </div>
                        <div v-if="memory?.open_threads" class="studio-row">
                            <dt>Открытые линии сюжета</dt>
                            <dd>{{ memory.open_threads }}</dd>
                        </div>
                        <div v-if="memory?.scene_closed != null" class="studio-row">
                            <dt>Предыдущая сцена</dt>
                            <dd>{{ memory.scene_closed ? 'Завершена' : 'Продолжается' }}</dd>
                        </div>
                    </dl>
                    <a href="#screenplay" class="underline">Вернуться к сценарию</a>
                </div>
            </section>
            <aside class="studio-panel memory-card">
                <ProjectArtwork :name="workspace.project.name" :cover="workspace.project.cover" />
                <h2>{{ workspace.project.name }}</h2>
                <small>Проект</small>
                <p>{{ workspace.project.name }}</p>
                <small>Сериал</small>
                <p>Эпизод {{ episodeNumber(workspace.episode.number) }}</p>
            </aside>
        </div>
    </div>
</template>
<style scoped>
.memory-screen {
    display: grid;
    gap: 20px;
}
.memory-columns {
    display: grid;
    grid-template-columns: minmax(0, 2.35fr) minmax(0, 1fr);
    align-items: start;
    gap: 20px;
}
.memory-panel,
.memory-card {
    min-width: 0;
}
.memory-frame {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding-block: 8px;
    font-size: 14px;
}
.memory-frame img {
    width: 82px;
    height: 56px;
    object-fit: cover;
    border-radius: 6px;
}
.memory-panel h2 {
    margin-bottom: 10px;
}
.memory-panel > button {
    margin-top: 14px;
    min-height: 44px;
}
.memory-details {
    margin-top: 14px;
}
.memory-details h3 {
    font-weight: 700;
}
.memory-card > div {
    aspect-ratio: 2.8;
    border-radius: 8px;
}
.memory-card h2 {
    margin-block: 12px;
}
.memory-card small {
    display: block;
    color: var(--muted-foreground);
    margin-top: 8px;
}
@media (max-width: 767px) {
    .memory-columns {
        grid-template-columns: 1fr;
    }
    .memory-screen {
        gap: 12px;
    }
}
</style>
