<script setup lang="ts">
import ShotCard from '@/components/episodes/ShotCard.vue';
import type { ContinuationSource, EpisodeRevisionSummary, EpisodeRouteParams } from '@/types/episodes';
import { preferredTake, takeStatus } from '@/lib/shotTakes';
import { computed } from 'vue';

const props = defineProps<{
    revision: EpisodeRevisionSummary;
    selectedTakeIds: number[];
    detail: number | null;
    params: EpisodeRouteParams;
    archived: boolean;
    projectEnabled: boolean;
    requestKey: string;
    readySourcesFor: (scenePosition: number, shotPosition: number) => ContinuationSource[];
}>();
const shots = computed(() => props.revision.scenes.flatMap((scene) => scene.shots));
const selected = computed(() => shots.value[props.detail ?? 0] ?? shots.value[0]);
const scene = computed(() => props.revision.scenes.find((scene) => scene.id === selected.value?.scene_id));
const shotNumbers = computed(() => new Map(shots.value.map((shot, index) => [shot.id, index + 1])));
function selectShot(event: Event) {
    if (event.target instanceof HTMLSelectElement) window.location.hash = 'shots/' + event.target.value;
}
</script>
<template>
    <div class="grid min-w-0 items-start gap-4 min-[851px]:grid-cols-[minmax(195px,1fr)_minmax(0,3fr)] min-[851px]:gap-6">
        <nav class="studio-panel hidden min-w-0 space-y-4 min-[851px]:block" aria-label="Кадры по сценам">
            <h2>Кадры</h2>
            <div v-for="group in revision.scenes" :key="group.id">
                <h3 class="text-muted-foreground mb-2 text-sm wrap-anywhere">Сцена {{ group.position }} · {{ group.title }}</h3>
                <a
                    v-for="shot in group.shots"
                    :key="shot.id"
                    :href="'#shots/' + shot.id"
                    :aria-current="selected?.id === shot.id ? 'true' : undefined"
                    class="aria-[current=true]:bg-accent aria-[current=true]:text-accent-foreground flex min-h-14 items-center gap-3 rounded-lg p-2 text-sm"
                >
                    <span class="bg-muted grid size-8 shrink-0 place-items-center rounded-md font-mono">{{ shotNumbers.get(shot.id) }}</span>
                    <span class="min-w-0"
                        ><strong class="line-clamp-2 wrap-anywhere">{{ shot.title || shot.action }}</strong
                        ><small>{{ takeStatus(preferredTake(shot.takes)) }}</small></span
                    >
                </a>
            </div>
        </nav>
        <label v-if="selected" class="studio-panel grid min-w-0 gap-2 min-[851px]:hidden"
            >Перейти к кадру
            <select class="bg-background w-full rounded-lg border" :value="selected.id" @change="selectShot">
                <optgroup v-for="group in revision.scenes" :key="group.id" :label="'Сцена ' + group.position + ' · ' + group.title">
                    <option v-for="shot in group.shots" :key="shot.id" :value="shot.id">
                        Кадр {{ shotNumbers.get(shot.id) }} · {{ shot.title || shot.action }}
                    </option>
                </optgroup>
            </select>
        </label>
        <ShotCard
            v-if="selected && scene"
            :key="selected.id"
            :shot="selected"
            :selected-take-ids="selectedTakeIds"
            :scene-title="'Сцена ' + scene.position + ' · ' + scene.title"
            :number="shotNumbers.get(selected.id)"
            :params="params"
            :revision-id="revision.id"
            :approved="revision.status === 'approved'"
            :archived="archived"
            :project-enabled="projectEnabled"
            :request-key="requestKey"
            :source-options="readySourcesFor(scene.position, selected.position)"
        />
        <p v-else class="studio-panel">В сценарии пока нет кадров.</p>
    </div>
</template>
