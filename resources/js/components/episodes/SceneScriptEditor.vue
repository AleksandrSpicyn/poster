<script setup lang="ts">
import ShotScriptFields from '@/components/episodes/ShotScriptFields.vue';
import { createScreenplayScene, createScreenplayShot, sourcePrecedes } from '@/lib/screenplay';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ContinuationSource, Screenplay } from '@/types/episodes';
import type { CharacterRevision } from '@/types/series';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

const model = defineModel<Screenplay>({ required: true });
const props = defineProps<{
    characters: (CharacterRevision & { character_name?: string })[];
    transitions: Record<string, string>;
    sources: ContinuationSource[];
    errors: Record<string, string | undefined>;
    disabled: boolean;
}>();
const shotCount = computed(() => model.value.scenes.reduce((count, scene) => count + scene.shots.length, 0));
const durationSeconds = computed(
    () => model.value.scenes.reduce((duration, scene) => duration + scene.shots.reduce((sum, shot) => sum + shot.duration_ms, 0), 0) / 1000,
);
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
    <fieldset :disabled="disabled" class="min-w-0 space-y-6 disabled:opacity-70">
        <legend class="sr-only">Episode screenplay</legend>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="script-title">Episode title</Label
                ><Input id="script-title" v-model="model.title" required maxlength="255" :aria-invalid="!!error('title')" /><InputError
                    :message="error('title')"
                />
            </div>
            <div class="grid gap-2">
                <Label for="script-objective">Episode goal</Label
                ><Input id="script-objective" v-model="model.objective" required maxlength="5000" :aria-invalid="!!error('objective')" /><InputError
                    :message="error('objective')"
                />
            </div>
        </div>
        <fieldset class="space-y-3 rounded-lg border p-4">
            <legend class="px-2 text-sm font-semibold">Characters and appearance versions</legend>
            <p class="text-muted-foreground text-xs">Choose one approved appearance for each character in this episode.</p>
            <label v-for="character in characters" :key="character.id" class="flex min-h-11 cursor-pointer items-center gap-3 text-sm"
                ><input
                    type="checkbox"
                    :checked="model.character_revision_ids.includes(character.id)"
                    class="size-4"
                    @change="toggleCharacter(character, $event)"
                /><span>{{ character.character_name ?? 'Character' }} · appearance version {{ character.number }}</span></label
            >
            <p v-if="!characters.length" class="text-muted-foreground text-sm">
                Approve an appearance version in the series character library first.
            </p>
            <InputError :message="error('character_revision_ids')" />
        </fieldset>
        <p class="text-muted-foreground text-sm" aria-live="polite">
            {{ model.scenes.length }} scenes · {{ shotCount }} shots · {{ durationSeconds }} seconds total (maximum 300)
        </p>
        <section v-for="(scene, sceneIndex) in model.scenes" :key="sceneIndex" class="space-y-5 rounded-xl border p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-semibold">Scene {{ sceneIndex + 1 }}</h3>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-11"
                        :disabled="sceneIndex === 0"
                        :aria-label="'Move scene ' + (sceneIndex + 1) + ' up'"
                        @click="move(model.scenes, sceneIndex, -1)"
                        ><ArrowUp class="size-4" aria-hidden="true" /></Button
                    ><Button
                        type="button"
                        variant="outline"
                        size="icon"
                        class="size-11"
                        :disabled="sceneIndex === model.scenes.length - 1"
                        :aria-label="'Move scene ' + (sceneIndex + 1) + ' down'"
                        @click="move(model.scenes, sceneIndex, 1)"
                        ><ArrowDown class="size-4" aria-hidden="true" /></Button
                    ><Button v-if="model.scenes.length > 1" type="button" variant="outline" class="h-11" @click="model.scenes.splice(sceneIndex, 1)"
                        ><Trash2 class="size-4" aria-hidden="true" />Remove scene</Button
                    >
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="'scene-title-' + sceneIndex">Scene title</Label
                    ><Input :id="'scene-title-' + sceneIndex" v-model="scene.title" required maxlength="255" /><InputError
                        :message="error('scenes.' + sceneIndex + '.title')"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="'scene-goal-' + sceneIndex">Scene goal</Label
                    ><Input :id="'scene-goal-' + sceneIndex" v-model="scene.objective" required maxlength="2000" /><InputError
                        :message="error('scenes.' + sceneIndex + '.objective')"
                    />
                </div>
                <div class="grid gap-2">
                    <Label :for="'scene-place-' + sceneIndex">Location</Label
                    ><Input
                        :id="'scene-place-' + sceneIndex"
                        v-model="scene.location"
                        required
                        maxlength="1000"
                        placeholder="For example, inside the café"
                    /><InputError :message="error('scenes.' + sceneIndex + '.location')" />
                </div>
                <div class="grid gap-2">
                    <Label :for="'scene-time-' + sceneIndex">Time</Label
                    ><Input
                        :id="'scene-time-' + sceneIndex"
                        v-model="scene.time"
                        required
                        maxlength="255"
                        placeholder="For example, the next morning"
                    /><InputError :message="error('scenes.' + sceneIndex + '.time')" />
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label :for="'scene-initial-' + sceneIndex">At the start</Label
                    ><textarea
                        :id="'scene-initial-' + sceneIndex"
                        v-model="scene.initial_state"
                        rows="3"
                        maxlength="4000"
                        class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                    /><InputError :message="error('scenes.' + sceneIndex + '.initial_state')" />
                </div>
                <div class="grid gap-2">
                    <Label :for="'scene-final-' + sceneIndex">Expected ending</Label
                    ><textarea
                        :id="'scene-final-' + sceneIndex"
                        v-model="scene.expected_final_state"
                        rows="3"
                        maxlength="4000"
                        class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
                    /><InputError :message="error('scenes.' + sceneIndex + '.expected_final_state')" />
                </div>
            </div>
            <label class="flex min-h-11 items-center gap-3 text-sm"
                ><input v-model="scene.is_closed" type="checkbox" class="size-4" />This scene should finish before the next scene starts</label
            >
            <section v-for="(shot, shotIndex) in scene.shots" :key="shotIndex" class="bg-muted/25 space-y-4 rounded-lg border p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h4 class="text-sm font-semibold">Shot {{ shotIndex + 1 }}</h4>
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11"
                            :disabled="shotIndex === 0"
                            aria-label="Move shot up"
                            @click="move(scene.shots, shotIndex, -1)"
                            ><ArrowUp class="size-4" aria-hidden="true" /></Button
                        ><Button
                            type="button"
                            variant="outline"
                            size="icon"
                            class="size-11"
                            :disabled="shotIndex === scene.shots.length - 1"
                            aria-label="Move shot down"
                            @click="move(scene.shots, shotIndex, 1)"
                            ><ArrowDown class="size-4" aria-hidden="true" /></Button
                        ><Button v-if="scene.shots.length > 1" type="button" variant="outline" class="h-11" @click="scene.shots.splice(shotIndex, 1)"
                            >Remove shot</Button
                        >
                    </div>
                </div>
                <ShotScriptFields
                    v-model="scene.shots[shotIndex]"
                    :scene-index="sceneIndex"
                    :shot-index="shotIndex"
                    :transitions="transitions"
                    :sources="sourcesFor(sceneIndex, shotIndex)"
                    :speakers="speakers"
                    :errors="errors"
                />
            </section>
            <Button type="button" variant="outline" class="h-11" :disabled="scene.shots.length >= 30 || shotCount >= 100" @click="addShot(sceneIndex)"
                ><Plus class="size-4" aria-hidden="true" />Add shot to this scene</Button
            >
        </section>
        <Button type="button" variant="outline" class="h-11" :disabled="model.scenes.length >= 20 || shotCount >= 100" @click="addScene"
            ><Plus class="size-4" aria-hidden="true" />Add scene</Button
        >
    </fieldset>
</template>
