<script setup lang="ts">
import TrimInterval from '@/components/episodes/TrimInterval.vue';
import type { ShotSummary, ShotTake } from '@/types/episodes';
import { computed } from 'vue';

const props = defineProps<{ shot: ShotSummary; readyTakes: ShotTake[]; selectedDuration: number | null }>();
const takeId = defineModel<number>('takeId', { required: true });
const start = defineModel<number>('start', { required: true });
const end = defineModel<number>('end', { required: true });
const takes = computed(() =>
    props.readyTakes.map((take) => ({
        id: take.id,
        take_number: take.take_number,
        image: take.media.find((media) => media.collection === 'shot-image')?.url,
    })),
);
const selected = computed(() => {
    const take = props.shot.takes.find((take) => take.id === takeId.value);
    return take?.media.find((media) => media.collection === 'shot-image')?.url;
});
const durationLabel = computed(() =>
    props.selectedDuration ? props.selectedDuration.toLocaleString('ru', { maximumFractionDigits: 3 }) + ' с' : '',
);
</script>

<template>
    <div class="trim-selection grid items-end gap-3 sm:grid-cols-3">
        <label class="trim-source-select text-muted-foreground text-sm"
            >Исходный дубль
            <select v-model="takeId" class="list-select w-full">
                <option v-for="take in takes" :key="take.id" :value="take.id">
                    Дубль {{ String(take.take_number).padStart(2, '0') }}{{ take.id === takeId && durationLabel ? ' · ' + durationLabel : '' }}
                </option>
            </select>
        </label>
        <fieldset class="min-w-0 space-y-2 sm:col-span-3">
            <legend class="text-muted-foreground text-sm max-sm:sr-only">Исходный дубль</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                <label v-for="take in takes" :key="take.id" class="take-option" :class="{ selected: takeId === take.id }">
                    <input v-model="takeId" type="radio" :name="'source-take-' + shot.id" :value="take.id" class="sr-only" />
                    <img v-if="take.image" :src="take.image" alt="" loading="lazy" />
                    <span
                        ><strong>Дубль {{ String(take.take_number).padStart(2, '0') }}</strong
                        ><small
                            >{{ takeId === take.id ? 'Выбран' : 'Готов'
                            }}<template v-if="take.id === takeId && durationLabel"> · {{ durationLabel }}</template></small
                        ></span
                    >
                </label>
            </div>
        </fieldset>
        <TrimInterval v-model:start="start" v-model:end="end" :shot-id="shot.id" :duration="shot.duration_ms" :thumbnail="selected" />
    </div>
</template>
<style scoped>
.trim-source-select {
    display: none;
}
.trim-detail-page .trim-selection {
    min-width: 0;
}
.take-option {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px;
    border: 1px solid var(--border);
    border-radius: 10px;
    cursor: pointer;
}
.trim-detail-page .take-option {
    min-height: 64px;
}
.trim-detail-page .take-option.selected strong {
    color: var(--ring);
}
@media (max-width: 639px) {
    .trim-selection:has(input[aria-invalid='true']) .trim-source-select {
        display: grid;
        gap: 4px;
    }
    .trim-selection:has(input[aria-invalid='true']) fieldset {
        display: none;
    }
    .trim-detail-page .take-option {
        padding: 0;
        min-height: 54px;
        border-color: transparent;
    }
    .trim-detail-page .take-option.selected {
        background: transparent;
    }
    .trim-detail-page .take-option img {
        width: 76px;
        height: 54px;
    }
}
.take-option.selected {
    background: var(--sidebar-accent);
    border-color: var(--primary);
}
.take-option:focus-within {
    outline: 2px solid var(--ring);
}
.take-option img {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: 8px;
}
.take-option small {
    display: block;
    color: var(--muted-foreground);
}
</style>
