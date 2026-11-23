<script setup lang="ts">
import AssemblyCard from '@/components/episodes/AssemblyCard.vue';
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
const recheck = computed(() => props.workspace.episode.validity === 'requires_review');
const otherVersions = computed(() => props.assemblies.filter((a) => a.id !== assembly.value?.id));
</script>
<template>
    <section class="grid gap-4">
        <nav v-if="assembly && !recheck" aria-label="Разделы публикации" class="workspace-tabs">
            <button
                v-for="item in sections.filter((item) => item.key !== 'results' || workspace.publications.length)"
                :key="item.key"
                class="rounded-md px-3 py-2 text-left"
                :aria-current="section === item.key ? 'step' : undefined"
                @click="section = item.key"
            >
                {{ item.label }}
            </button>
        </nav>
        <div>
            <section v-if="recheck" class="studio-panel grid gap-4">
                <h2>Требует перепроверки</h2>
                <p>После принятия этой версии изменился финал предыдущего эпизода.</p>
                <p class="studio-row">
                    <strong>Эпизод</strong>{{ workspace.episode.title }} · выпуск {{ String(workspace.episode.number).padStart(2, '0') }}
                </p>
                <p v-if="workspace.sourceAssembly?.continuity_snapshot.character_state" class="studio-row">
                    <strong>Что проверить</strong>{{ workspace.sourceAssembly.continuity_snapshot.character_state }}
                </p>
                <Button as-child class="justify-self-start"><a href="#screenplay">Открыть перепроверку</a></Button>
            </section>
            <div v-else-if="!assembly" class="studio-panel grid justify-items-start gap-3">
                <p>Для публикации сначала соберите монтаж эпизода.</p>
                <Button as-child><a href="#montage">Перейти к монтажу</a></Button>
            </div>
            <AssemblyCard
                v-else
                :key="assembly.id + ':' + assembly.lock_version"
                :assembly="assembly"
                :episode-title="workspace.episode.title"
                :project="workspace.project"
                :episode-number="workspace.episode.number"
                :caption="workspace.screenplay?.public_caption"
                :section="section"
                :params="{ series: workspace.series.id, episode: workspace.episode.id }"
                :channels="workspace.channels.filter((c) => ['youtube', 'instagram', 'tiktok'].includes(c.type))"
                :publications="workspace.publications.filter((p) => p.episode_assembly_id === assembly.id)"
                :can-publish="workspace.series.output_mode !== 'single_video'"
                :current-accepted="workspace.episode.accepted_assembly_id === assembly.id && workspace.episode.validity === 'current'"
                :archived="!!workspace.series.archived_at"
                :project-enabled="workspace.project.is_active"
                @navigate="section = $event"
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
    </section>
</template>
