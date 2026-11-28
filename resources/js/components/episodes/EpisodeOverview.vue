<script setup lang="ts">
import type { EpisodeRevisionSummary, Screenplay } from '@/types/episodes';
import { computed } from 'vue';

const props = defineProps<{
    panel: string;
    revision: EpisodeRevisionSummary;
    screenplay: Screenplay;
    selection: { prompt_id: number; trim_start_ms: number; trim_end_ms: number }[];
}>();
const emit = defineEmits<{ open: [number] }>();
const padded = (value: number) => String(value).padStart(2, '0');
const shots = computed(() => props.revision.scenes.flatMap((scene) => scene.shots));
const fragments = computed(() => {
    let offset = 0;
    return shots.value.map((shot, i) => {
        const item = props.selection[i];
        const duration = item ? Math.max(0, item.trim_end_ms - item.trim_start_ms) : shot.duration_ms;
        const start = offset;
        offset += duration;
        return { duration, time: padded(Math.floor(start / 60000)) + ':' + padded(Math.floor(start / 1000) % 60) };
    });
});
const rows = computed(() => {
    if (props.panel === 'screenplay')
        return props.screenplay.scenes.map((scene, i) => ({ name: 'Сцена ' + (i + 1), description: scene.objective || scene.title }));
    return shots.value.map((shot, i) => {
        const take = shot.takes.find((candidate) => candidate.id === props.selection[i]?.prompt_id);
        return {
            name: 'Кадр ' + padded(i + 1),
            description: take
                ? 'Дубль ' +
                  padded(take.take_number) +
                  ' · ' +
                  new Intl.NumberFormat('ru', { maximumFractionDigits: 3 }).format(fragments.value[i].duration / 1000) +
                  ' с'
                : 'Выберите готовый дубль',
        };
    });
});
</script>
<template>
    <section class="episode-overview">
        <h2>{{ panel === 'screenplay' ? 'Сценарий' : 'Монтаж' }}</h2>
        <button v-for="(row, i) in rows" :key="i" type="button" class="overview-row" @click="emit('open', i)">
            <strong>{{ row.name }}</strong><span>{{ row.description }}</span>
        </button>
        <div v-if="panel !== 'screenplay' && fragments.length" class="overview-track">
            <p>Длительность фрагментов</p>
            <div>
                <button
                    v-for="(fragment, i) in fragments"
                    :key="i"
                    type="button"
                    :style="{ flexGrow: fragment.duration || 1 }"
                    :aria-label="'Кадр ' + (i + 1) + ', ' + fragment.time"
                    @click="emit('open', i)"
                >
                    {{ fragment.time }}
                </button>
            </div>
        </div>
    </section>
</template>
<style scoped>
.episode-overview h2 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 12px;
}
.overview-row {
    display: grid;
    width: 100%;
    grid-template-columns: 45% 1fr;
    gap: 12px;
    align-items: start;
    text-align: left;
    border-bottom: 1px solid var(--border);
    padding: 12px 0 17px;
}
.overview-row span {
    color: var(--muted-foreground);
    font-size: 14px;
}
.overview-row:hover strong,
.overview-row:focus-visible strong {
    text-decoration: underline;
}
.overview-track {
    margin-top: 24px;
}
.overview-track p {
    color: var(--muted-foreground);
    font-size: 14px;
    margin-bottom: 10px;
}
.overview-track div {
    display: flex;
    gap: 4px;
}
.overview-track button {
    min-width: 0;
    flex-basis: 0;
    background: var(--sidebar-accent);
    color: var(--sidebar-accent-foreground);
    border-radius: 6px;
    font: 12px monospace;
    padding: 10px 5px;
    text-align: left;
}
@media (max-width: 639px) {
    .episode-overview h2 {
        margin-bottom: 16px;
    }
    .overview-row {
        min-height: 67px;
        align-content: center;
        grid-template-columns: 1fr;
        gap: 2px;
        padding: 8px 0;
    }
    .overview-row span {
        font-size: 14px;
    }
}
</style>
