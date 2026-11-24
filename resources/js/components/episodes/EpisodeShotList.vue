<script setup lang="ts">
import ShotCard from '@/components/episodes/ShotCard.vue';
import type { ContinuationSource, EpisodeRevisionSummary, EpisodeRouteParams } from '@/types/episodes';
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
const selectedShotId = computed(() => (props.detail === null ? null : shots.value[props.detail]?.id));
const shotNumbers = computed(() => new Map(shots.value.map((shot, index) => [shot.id, index + 1])));
</script>

<template>
    <section class="space-y-5" aria-labelledby="episode-shots-title">
        <h2 id="episode-shots-title" class="text-lg font-semibold">Кадры и дубли</h2>
        <p v-if="revision.status !== 'approved'" class="text-muted-foreground text-sm">Утвердите сценарий, чтобы создавать кадры.</p>
        <section
            v-for="scene in revision.scenes"
            v-show="detail === null || scene.shots.some((item) => item.id === selectedShotId)"
            :key="scene.id"
            class="space-y-4"
        >
            <div>
                <h3 class="font-semibold">{{ scene.position }}. {{ scene.title }}</h3>
                <p class="text-muted-foreground mt-1 text-xs">
                    {{ scene.is_closed ? 'Сцена завершается' : 'Сцена продолжается' }}
                </p>
            </div>
            <ShotCard
                v-for="shot in scene.shots"
                v-show="detail === null || shot.id === selectedShotId"
                :key="shot.id"
                :shot="shot"
                :selected-take-ids="selectedTakeIds"
                :scene-title="scene.title"
                :number="shotNumbers.get(shot.id)"
                :params="params"
                :revision-id="revision.id"
                :approved="revision.status === 'approved'"
                :archived="archived"
                :project-enabled="projectEnabled"
                :request-key="requestKey"
                :source-options="readySourcesFor(scene.position, shot.position)"
            />
        </section>
    </section>
</template>
