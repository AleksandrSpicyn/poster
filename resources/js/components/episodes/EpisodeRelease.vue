<script setup lang="ts">
import AssemblyCard from '@/components/episodes/AssemblyCard.vue';
import ProjectArtwork from '@/components/projects/ProjectArtwork.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { EpisodeAssemblySummary, EpisodeWorkspaceProps } from '@/types/episodes';
import { computed, ref } from 'vue';

const props = defineProps<{ workspace: EpisodeWorkspaceProps; assemblies: EpisodeAssemblySummary[] }>();
const sections = [
    { key: 'preview', label: 'Предпросмотр' },
    { key: 'recipients', label: 'Получатели' },
    { key: 'confirmation', label: 'Подтверждение' },
    { key: 'results', label: 'Результаты' },
] as const;
const section = ref<(typeof sections)[number]['key']>(props.workspace.publications.length ? 'results' : 'preview');
const selectedId = ref<number | null>(null);
const assembly = computed(
    () =>
        props.assemblies.find((a) => a.id === selectedId.value) ??
        props.assemblies.find((a) => a.id === props.workspace.episode.accepted_assembly_id) ??
        props.assemblies[0],
);
const reviewing = ref(false);
const mixed = computed(
    () =>
        section.value === 'results' &&
        props.workspace.publications.some((item) => item.episode_assembly_id === assembly.value?.id && item.status === 'submission_unknown'),
);
const otherVersions = computed(() => props.assemblies.filter((a) => a.id !== assembly.value?.id));
</script>
<template>
    <div class="release-page" :class="{ detail: section !== 'preview', mixed }">
        <header class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="section === 'preview' ? 'Публикация эпизода' : 'Выпуск эпизода'"
                :description="
                    section === 'preview'
                        ? workspace.series.name + (workspace.episode.accepted_assembly_id ? ' / Принятая сборка' : '')
                        : mixed
                          ? reviewing
                              ? 'Сверка вручную'
                              : 'Результаты'
                          : undefined
                "
            />
            <Button v-if="section === 'preview'" class="h-11" :disabled="!assembly" @click="section = 'confirmation'">Подтвердить отправку</Button>
        </header>
        <div class="release-grid">
            <nav aria-label="Разделы публикации" class="studio-panel flex flex-wrap gap-2 min-[1100px]:grid">
                <h2 class="hidden font-bold min-[1100px]:block">В этом разделе</h2>
                <button
                    v-for="item in sections.filter((item) => item.key !== 'results' || section !== 'preview' || workspace.publications.length)"
                    :key="item.key"
                    class="rounded-md px-3 py-2 text-left"
                    :aria-current="section === item.key ? 'step' : undefined"
                    @click="section = item.key"
                >
                    {{ item.label }}
                </button>
            </nav>
            <div>
                <p v-if="!assembly">Здесь появятся сборки для просмотра и приёмки.</p>
                <AssemblyCard
                    v-else
                    :key="assembly.id + ':' + assembly.lock_version"
                    :assembly="assembly"
                    :episode-title="workspace.episode.title"
                    :project="workspace.project"
                    @review="reviewing = $event"
                    :episode-number="workspace.episode.number"
                    :caption="workspace.screenplay?.public_caption"
                    :section="section"
                    @navigate="section = $event"
                    :params="{ series: workspace.series.id, episode: workspace.episode.id }"
                    :channels="workspace.channels.filter((c) => ['youtube', 'instagram', 'tiktok'].includes(c.type))"
                    :publications="workspace.publications.filter((p) => p.episode_assembly_id === assembly.id)"
                    :can-publish="workspace.series.output_mode !== 'single_video'"
                    :current-accepted="workspace.episode.accepted_assembly_id === assembly.id && workspace.episode.validity === 'current'"
                    :archived="!!workspace.series.archived_at"
                    :project-enabled="workspace.project.is_active"
                />
                <details v-if="otherVersions.length" class="mt-3">
                    <summary>Другие версии сборки</summary>
                    <button
                        v-for="item in otherVersions"
                        :key="item.id"
                        class="block p-2"
                        @click="
                            selectedId = item.id;
                            section = 'preview';
                        "
                    >
                        Версия {{ item.number }}
                    </button>
                </details>
            </div>
            <aside v-if="section !== 'preview'" class="studio-panel hidden min-[1100px]:block">
                <ProjectArtwork :name="workspace.project.name" :cover="workspace.project.cover" class="mb-3 aspect-[2.4] w-full rounded-lg" />
                <h2>{{ workspace.episode.title }}</h2>
                <p>Проект · {{ workspace.project.name }}</p>
                <p v-if="assembly">Версия {{ String(assembly.number).padStart(2, '0') }}</p>
            </aside>
        </div>
        <nav aria-label="Этапы эпизода" class="mt-6 flex flex-wrap gap-4">
            <button
                v-if="mixed"
                type="button"
                @click="
                    section = 'preview';
                    reviewing = false;
                "
            >
                Предпросмотр
            </button>
            <a href="#screenplay">Сценарий</a><a href="#shots">Кадры</a><a href="#montage">Монтаж</a>
        </nav>
    </div>
</template>
<style scoped>
.mixed .release-grid nav {
    display: none;
}
@media (min-width: 1100px) {
    .mixed .release-grid {
        grid-template-columns: minmax(0, 2.23fr) minmax(0, 1fr);
    }
}

.detail .release-grid nav {
    display: flex;
    min-height: 0;
    gap: 6px;
    flex-wrap: wrap;
    grid-column: 1/-1;
    padding: 0;
    border: 0;
    background: transparent;
}
.detail .release-grid nav h2 {
    display: none;
}
.detail .release-grid nav button {
    background: var(--muted);
}
@media (min-width: 1100px) {
    .detail .release-grid {
        grid-template-columns: minmax(0, 2.35fr) minmax(0, 1fr);
        max-width: 1236px;
    }
}

.release-page header {
    margin-bottom: 20px;
}
.release-grid {
    display: grid;
    align-items: start;
    gap: 16px;
}
.release-grid nav [aria-current] {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 14%, var(--card));
}
@media (min-width: 1100px) {
    .release-grid {
        grid-template-columns: 276px minmax(0, 852px);
    }
    .release-grid nav {
        min-height: 240px;
        align-content: start;
    }
}
@media (min-width: 1920px) {
    .release-grid {
        grid-template-columns: 276px minmax(0, 820px);
    }
}
@media (max-width: 1099px) {
    .release-page header {
        flex-direction: column;
    }
    .release-grid nav {
        padding: 0;
        border: 0;
        background: transparent;
    }
    .release-grid nav button {
        background: var(--muted);
    }
}
</style>
