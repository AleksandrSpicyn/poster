<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import type { EpisodeWorkspaceProps, ScreenplayShot, ShotCue } from '@/types/episodes';
import type { CharacterRevision } from '@/types/series';
import { Button } from '@/components/ui/button';
const shot = defineModel<ScreenplayShot>({ required: true });
defineProps<{ speakers: (CharacterRevision & { character_name?: string })[] }>();
const page = usePage<SharedData & EpisodeWorkspaceProps>();
function cue(kind: ShotCue['kind']) {
    shot.value.direction?.cues.push({ kind, text: '', start_ms: 0, end_ms: shot.value.duration_ms, asset_id: null });
}
function time(event: Event): number | null {
    const input = event.target as HTMLInputElement;
    return input.value === '' ? null : input.valueAsNumber;
}
</script>
<template>
    <details class="grid gap-3 rounded border p-3">
        <summary>Постановка и таймкоды</summary>
        <Button
            v-if="!shot.direction"
            type="button"
            variant="outline"
            @click="shot.direction = { visible_character_ids: null, reference_ids: [], cues: [], review_frame: true }"
            >Настроить кадр</Button
        >
        <div v-else class="grid gap-3 py-2">
            <label>Постановка и звук <textarea v-model="shot.direction.notes" maxlength="3000" class="block w-full rounded border p-2" /></label>
            <label><input v-model="shot.direction.review_frame" type="checkbox" /> Согласовать первый кадр до генерации видео</label>
            <label
                ><input
                    type="checkbox"
                    :checked="shot.direction.visible_character_ids !== null"
                    @change="shot.direction.visible_character_ids = shot.direction.visible_character_ids === null ? [] : null"
                />
                Выбрать, кто виден в кадре</label
            >
            <div v-if="shot.direction.visible_character_ids !== null">
                <label v-for="person in speakers" :key="person.id" class="mr-3"
                    ><input v-model="shot.direction.visible_character_ids" type="checkbox" :value="person.character_id" />
                    {{ person.character_name }}</label
                >
            </div>
            <fieldset>
                <legend>Изображения для первого кадра</legend>
                <label
                    v-for="asset in page.props.series.assets?.filter((a) => a.kind !== 'sound' && a.kind !== 'season')"
                    :key="asset.id"
                    class="mr-3"
                    ><input v-model="shot.direction.reference_ids" type="checkbox" :value="asset.id" /> {{ asset.label }}</label
                >
                <div
                    v-for="person in speakers.filter(
                        (p) => shot.direction?.visible_character_ids == null || shot.direction.visible_character_ids.includes(p.character_id),
                    )"
                    :key="person.id"
                >
                    <label v-for="refImage in person.references" :key="refImage.id" class="mr-3 inline-flex items-center gap-1"
                        ><input v-model="shot.direction.reference_ids" type="checkbox" :value="refImage.id" /><img
                            :src="refImage.url"
                            class="size-9 object-contain"
                            alt=""
                        />{{ person.character_name }} · {{ refImage.label }}</label
                    >
                </div>
            </fieldset>
            <div v-for="(line, i) in shot.dialogue" :key="i" class="flex flex-wrap items-center gap-2">
                <span>{{ line.text || 'Реплика ' + (i + 1) }}</span>
                <label
                    >Начало, мс <input type="number" min="0" :max="shot.duration_ms" :value="line.start_ms" @input="line.start_ms = time($event)"
                /></label>
                <label
                    >Конец, мс <input type="number" min="1" :max="shot.duration_ms" :value="line.end_ms" @input="line.end_ms = time($event)"
                /></label>
            </div>
            <div v-for="(item, i) in shot.direction.cues" :key="i" class="grid gap-1 rounded border p-2">
                <label
                    >{{ item.kind === 'sound' ? 'Звук' : item.kind === 'text' ? 'Титр' : 'Вставка' }}
                    <input v-model="item.text" required maxlength="500"
                /></label>
                <label>Начало, мс <input v-model.number="item.start_ms" type="number" min="0" :max="shot.duration_ms" required /></label>
                <label>Конец, мс <input v-model.number="item.end_ms" type="number" min="1" :max="shot.duration_ms" required /></label>
                <label v-if="item.kind !== 'text'"
                    >Материал
                    <select v-model="item.asset_id">
                        <option :value="null">{{ item.kind === 'sound' ? 'Сгенерировать по описанию' : 'Выберите изображение' }}</option>
                        <option v-for="asset in page.props.series.assets?.filter((a) => a.kind === item.kind)" :key="asset.id" :value="asset.id">
                            {{ asset.label }}
                        </option>
                    </select></label
                >
                <Button type="button" variant="outline" @click="shot.direction.cues.splice(i, 1)">Удалить</Button>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-for="kind in ['sound', 'text', 'insert'] as const" :key="kind" type="button" variant="outline" @click="cue(kind)">{{
                    kind === 'sound' ? 'Добавить звук' : kind === 'text' ? 'Добавить титр' : 'Добавить вставку'
                }}</Button>
            </div>
        </div>
    </details>
</template>
