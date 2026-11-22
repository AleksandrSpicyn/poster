<script setup lang="ts">
import ShotScriptFields from '@/components/episodes/ShotScriptFields.vue';
import { createScreenplayScene, createScreenplayShot, sourcePrecedes } from '@/lib/screenplay';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { workspaceSubscription } from '@/composables/useWorkspaceRealtime';
import type { ContinuationSource, Screenplay } from '@/types/episodes';
import type { CharacterRevision } from '@/types/series';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { computed, inject, ref, watch } from 'vue';

const model = defineModel<Screenplay>({ required: true });
const props = defineProps<{
    characters: (CharacterRevision & { character_name?: string })[];
    sceneIndex?: number | null;
    thumbnails?: (string | undefined)[][];
    transitions: Record<string, string>;
    sources: ContinuationSource[];
    errors: Record<string, string | undefined>;
    disabled: boolean;
    online: boolean;
    draftStored: boolean;
}>();
const subscription = inject(workspaceSubscription, null);
const recoveringLine = defineModel<string>('recoveringLine', { required: true });
const interrupted = ref(!props.online);
const dialogueConnected = computed(() => props.online && (subscription?.value ?? true));

function rememberActiveLine() {
    const element = document.activeElement;
    if (interrupted.value && !dialogueConnected.value && element instanceof HTMLInputElement && element.id.startsWith('dialogue-')) {
        recoveringLine.value = element.id;
    }
}
watch([dialogueConnected, () => props.online], ([isConnected, online], [wasConnected]) => {
    if (!isConnected && (wasConnected || !online)) {
        interrupted.value = true;
        rememberActiveLine();
    }
});
const shotCount = computed(() => model.value.scenes.reduce((count, scene) => count + scene.shots.length, 0));
const durationSeconds = computed(
    () => model.value.scenes.reduce((duration, scene) => duration + scene.shots.reduce((sum, shot) => sum + shot.duration_ms, 0), 0) / 1000,
);
const shotPlural = new Intl.PluralRules('ru');
function shotCountLabel(count: number) {
    const form = shotPlural.select(count);
    return count + ' ' + (form === 'one' ? 'кадр' : form === 'few' ? 'кадра' : 'кадров');
}
const speakers = computed(() => props.characters.filter((character) => model.value.character_revision_ids.includes(character.id)));

function addScene() {
    model.value.scenes.push(createScreenplayScene());
}
function addShot(sceneIndex: number) {
    model.value.scenes[sceneIndex].shots.push(createScreenplayShot());
}
function move<T>(items: T[], index: number, delta: number) {
    const target = index + delta;
    if (target < 0 || target >= items.length) return;
    [items[index], items[target]] = [items[target], items[index]];
}
function toggleCharacter(character: CharacterRevision, event: Event) {
    const selected = (event.target as HTMLInputElement).checked;
    const versions = props.characters.filter((item) => item.character_id === character.character_id).map((item) => item.id);
    model.value.character_revision_ids = model.value.character_revision_ids.filter((id) => !versions.includes(id));
    if (selected) model.value.character_revision_ids.push(character.id);
}
function sourcesFor(sceneIndex: number, shotIndex: number) {
    return props.sources.filter((source) => sourcePrecedes(source, sceneIndex + 1, shotIndex + 1));
}
function error(path: string): string | undefined {
    return props.errors['script.' + path];
}
</script>

<template>
    <fieldset
        :disabled="disabled"
        class="screenplay-fields min-w-0 space-y-6 disabled:opacity-70"
        :class="{ 'scene-focused': props.sceneIndex != null }"
        @focusin="rememberActiveLine"
    >
        <legend class="sr-only">Сценарий эпизода</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="script-title">Название эпизода</Label
                ><Input id="script-title" v-model="model.title" required maxlength="255" :aria-invalid="!!error('title')" /><InputError
                    :message="error('title')"
                />
            </div>
            <div class="grid gap-2">
                <Label for="script-objective">Цель эпизода</Label
                ><Input id="script-objective" v-model="model.objective" required maxlength="5000" :aria-invalid="!!error('objective')" /><InputError
                    :message="error('objective')"
                />
            </div>
        </div>
        <div class="grid gap-2">
            <Label for="script-caption">Подпись для Instagram и YouTube</Label>
            <textarea id="script-caption" v-model="model.public_caption" rows="2" maxlength="1000" class="rounded-md border p-2" />
            <InputError :message="error('public_caption')" />
        </div>
        <fieldset class="space-y-3 rounded-lg border p-4">
            <legend class="px-2 text-sm font-semibold">Персонажи и версии образов</legend>
            <p class="text-muted-foreground text-xs">Выберите утверждённый образ для каждого персонажа эпизода.</p>
            <label v-for="character in characters" :key="character.id" class="flex min-h-11 cursor-pointer items-center gap-3 text-sm"
                ><input
                    type="checkbox"
                    :checked="model.character_revision_ids.includes(character.id)"
                    class="size-4"
                    @change="toggleCharacter(character, $event)"
                /><span>{{ character.character_name ?? 'Персонаж' }} · версия образа {{ character.number }}</span></label
            >
            <p v-if="!characters.length" class="text-muted-foreground text-sm">Сначала утвердите версию образа в библиотеке персонажей сериала.</p>
            <InputError :message="error('character_revision_ids')" />
        </fieldset>
        <p class="text-muted-foreground text-sm" aria-live="polite">
            Сцен: {{ model.scenes.length }} · кадров: {{ shotCount }} · {{ durationSeconds }} из 300 с
        </p>
        <section
            v-for="(scene, sceneIndex) in model.scenes"
            :key="sceneIndex"
            v-show="props.sceneIndex == null || props.sceneIndex === sceneIndex"
            class="script-scene space-y-5 rounded-xl border p-4 sm:p-5"
        >
            <div class="scene-settings space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold">
                        Сцена {{ String(sceneIndex + 1).padStart(2, '0') }}
                        <small class="text-muted-foreground font-normal">{{ shotCountLabel(scene.shots.length) }}</small>
                    </h3>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11"
                            :disabled="sceneIndex === 0"
                            :aria-label="'Переместить сцену ' + (sceneIndex + 1) + ' вверх'"
                            @click="move(model.scenes, sceneIndex, -1)"
                            ><ArrowUp class="size-4" aria-hidden="true" /></Button
                        ><Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11"
                            :disabled="sceneIndex === model.scenes.length - 1"
                            :aria-label="'Переместить сцену ' + (sceneIndex + 1) + ' вниз'"
                            @click="move(model.scenes, sceneIndex, 1)"
                            ><ArrowDown class="size-4" aria-hidden="true" /></Button
                        ><Button
                            v-if="model.scenes.length > 1"
                            type="button"
                            variant="outline"
                            class="h-11"
                            @click="model.scenes.splice(sceneIndex, 1)"
                            ><Trash2 class="size-4" aria-hidden="true" />Удалить сцену</Button
                        >
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="'scene-title-' + sceneIndex">Название сцены</Label
                        ><Input :id="'scene-title-' + sceneIndex" v-model="scene.title" required maxlength="255" /><InputError
                            :message="error('scenes.' + sceneIndex + '.title')"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="'scene-goal-' + sceneIndex">Цель сцены</Label
                        ><Input :id="'scene-goal-' + sceneIndex" v-model="scene.objective" required maxlength="2000" /><InputError
                            :message="error('scenes.' + sceneIndex + '.objective')"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="'scene-place-' + sceneIndex">Место</Label
                        ><Input
                            :id="'scene-place-' + sceneIndex"
                            v-model="scene.location"
                            required
                            maxlength="1000"
                            placeholder="Например, в кафе"
                        /><InputError :message="error('scenes.' + sceneIndex + '.location')" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="'scene-time-' + sceneIndex">Время</Label
                        ><Input
                            :id="'scene-time-' + sceneIndex"
                            v-model="scene.time"
                            required
                            maxlength="255"
                            placeholder="Например, на следующее утро"
                        /><InputError :message="error('scenes.' + sceneIndex + '.time')" />
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label :for="'scene-initial-' + sceneIndex">В начале сцены</Label
                        ><textarea
                            :id="'scene-initial-' + sceneIndex"
                            v-model="scene.initial_state"
                            rows="1"
                            maxlength="4000"
                            class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                        /><InputError :message="error('scenes.' + sceneIndex + '.initial_state')" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="'scene-final-' + sceneIndex">Ожидаемый финал</Label
                        ><textarea
                            :id="'scene-final-' + sceneIndex"
                            v-model="scene.expected_final_state"
                            rows="1"
                            maxlength="4000"
                            class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                        /><InputError :message="error('scenes.' + sceneIndex + '.expected_final_state')" />
                    </div>
                </div>
                <label class="flex min-h-11 items-center gap-3 text-sm"
                    ><input v-model="scene.is_closed" type="checkbox" class="size-4" />Завершить сцену перед началом следующей</label
                >
            </div>
            <div class="shot-forms">
                <section v-for="(shot, shotIndex) in scene.shots" :key="shotIndex" class="bg-muted/25 space-y-4 rounded-lg border p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h4 class="font-semibold">Кадр {{ String(shotIndex + 1).padStart(2, '0') }}</h4>
                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="size-11"
                                :disabled="shotIndex === 0"
                                aria-label="Переместить кадр вверх"
                                @click="move(scene.shots, shotIndex, -1)"
                                ><ArrowUp class="size-4" aria-hidden="true" /></Button
                            ><Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="size-11"
                                :disabled="shotIndex === scene.shots.length - 1"
                                aria-label="Переместить кадр вниз"
                                @click="move(scene.shots, shotIndex, 1)"
                                ><ArrowDown class="size-4" aria-hidden="true" /></Button
                            ><Button
                                v-if="scene.shots.length > 1"
                                type="button"
                                variant="outline"
                                class="h-11"
                                @click="scene.shots.splice(shotIndex, 1)"
                                >Удалить кадр</Button
                            >
                        </div>
                    </div>
                    <div v-if="thumbnails?.[sceneIndex]?.[shotIndex]" class="flex items-center gap-3">
                        <img class="size-16 rounded-md object-cover" :src="thumbnails[sceneIndex][shotIndex]" alt="" /><span>{{ shot.camera }}</span>
                    </div>
                    <ShotScriptFields
                        v-model="scene.shots[shotIndex]"
                        :scene-index="sceneIndex"
                        :recovering-line="recoveringLine"
                        :connected="dialogueConnected"
                        :subscribed="!!subscription"
                        :draft-stored="draftStored"
                        :shot-index="shotIndex"
                        :transitions="transitions"
                        :sources="sourcesFor(sceneIndex, shotIndex)"
                        :speakers="speakers"
                        :errors="errors"
                    />
                </section>
            </div>
            <Button type="button" variant="outline" class="h-11" :disabled="scene.shots.length >= 30 || shotCount >= 100" @click="addShot(sceneIndex)"
                ><Plus class="size-4" aria-hidden="true" />Добавить кадр в сцену</Button
            >
        </section>
        <Button type="button" variant="outline" class="h-11" :disabled="model.scenes.length >= 20 || shotCount >= 100" @click="addScene"
            ><Plus class="size-4" aria-hidden="true" />Добавить сцену</Button
        >
    </fieldset>
</template>

<style scoped>
.shot-forms {
    display: grid;
    gap: 12px;
}
@media (min-width: 768px) {
    .shot-forms {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
.scene-focused {
    display: flex;
    flex-direction: column;
    align-items: stretch;
}
.scene-focused .script-scene {
    border: 0;
    padding: 0;
}
.scene-focused .scene-settings,
.scene-focused .shot-forms > section {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: clamp(14px, 1.3vw, 22px);
}
.scene-settings small {
    margin-inline-start: 5px;
}
.scene-focused > section {
    order: -1;
}
</style>
