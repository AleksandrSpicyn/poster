<script setup lang="ts">
import AssemblyCard from '@/components/episodes/AssemblyCard.vue';
import EpisodePublicationHistory from '@/components/episodes/EpisodePublicationHistory.vue';
import type { EpisodeAssemblySummary, EpisodeWorkspaceProps } from '@/types/episodes';
import { Check, CircleAlert, Clock } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps<{ workspace: EpisodeWorkspaceProps; assemblies: EpisodeAssemblySummary[] }>();
const selectedId = ref<number | null>(null);
const assembly = computed(
    () =>
        props.assemblies.find((a) => a.id === selectedId.value) ??
        props.assemblies.find((a) => a.id === props.workspace.episode.accepted_assembly_id) ??
        props.assemblies[0],
);
const params = computed(() => ({ series: props.workspace.series.id, episode: props.workspace.episode.id }));
const states: Record<string, string> = {
    draft: 'Черновик',
    queued: 'В очереди',
    building: 'Идёт сборка',
    ready: 'Готова к проверке',
    accepted: 'Принята',
    failed: 'Ошибка сборки',
};
</script>
<template>
    <section class="bg-card text-card-foreground min-w-0 rounded-xl border px-4 min-[700px]:px-5 min-[1100px]:px-6 min-[1920px]:px-7">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b py-4">
            <h2 class="text-xl font-semibold">Публикация эпизода</h2>
            <div v-if="assembly" class="flex flex-wrap items-center gap-3 text-sm">
                <select
                    v-if="assemblies.length > 1"
                    aria-label="Версия сборки"
                    class="studio-field"
                    :value="assembly.id"
                    @change="selectedId = Number(($event.target as HTMLSelectElement).value)"
                >
                    <option v-for="item in assemblies" :key="item.id" :value="item.id">Версия {{ item.number }}</option>
                </select>
                <span v-else class="font-mono">Версия {{ assembly.number }}</span>
                <span
                    class="inline-flex items-center gap-1.5"
                    :class="
                        assembly.status === 'failed' ? 'text-destructive' : assembly.status === 'accepted' ? 'text-ring' : 'text-muted-foreground'
                    "
                >
                    <component
                        :is="assembly.status === 'accepted' ? Check : assembly.status === 'failed' ? CircleAlert : Clock"
                        class="size-4"
                        aria-hidden="true"
                    />
                    {{ states[assembly.status] ?? assembly.status }}
                </span>
            </div>
        </header>
        <div v-if="workspace.episode.validity === 'requires_review'" class="grid justify-items-start gap-2 py-4">
            <h3 class="font-semibold">Требует перепроверки</h3>
            <p>После принятия этой версии изменился финал предыдущего эпизода.</p>
            <p v-if="workspace.sourceAssembly?.continuity_snapshot.character_state">
                {{ workspace.sourceAssembly.continuity_snapshot.character_state }}
            </p>
            <a class="inline-flex min-h-11 items-center underline" href="#screenplay">Открыть перепроверку</a>
        </div>
        <AssemblyCard v-else-if="assembly" :key="assembly.id" :assembly="assembly" :workspace="workspace" :params="params" />
        <div v-else class="py-4">
            <p>Для публикации сначала соберите монтаж эпизода.</p>
            <a class="inline-flex min-h-11 items-center underline" href="#montage">Перейти к монтажу</a>
        </div>
        <EpisodePublicationHistory :publications="workspace.publications" :assemblies="assemblies" :params="params" />
    </section>
</template>
