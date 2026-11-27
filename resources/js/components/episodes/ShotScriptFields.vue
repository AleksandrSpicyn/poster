<script setup lang="ts">
import ShotDirectionEditor from '@/components/episodes/ShotDirectionEditor.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ContinuationSource, ScreenplayShot } from '@/types/episodes';
import type { CharacterRevision } from '@/types/series';
import { CircleAlert, CircleCheck, Plus } from '@lucide/vue';
import { computed } from 'vue';

const shot = defineModel<ScreenplayShot>({ required: true });
const props = defineProps<{
    sceneIndex: number;
    recoveringLine: string;
    connected: boolean;
    subscribed: boolean;
    draftStored: boolean;
    shotIndex: number;
    transitions: Record<string, string>;
    sources: ContinuationSource[];
    speakers: (CharacterRevision & { character_name?: string })[];
    errors: Record<string, string | undefined>;
}>();
const connectionMessage = computed(() => {
    if (props.connected) {
        return props.subscribed ? 'Подписка возобновлена. Черновик готов к сохранению.' : 'Черновик готов к сохранению.';
    }
    return props.draftStored ? 'Введённый текст сохранён в этой вкладке.' : 'Оставьте вкладку открытой до сохранения.';
});
function lineId(index: number) {
    return `dialogue-${props.sceneIndex}-${props.shotIndex}-${index}`;
}
function sourceValue(shot: ScreenplayShot): string {
    return shot.source_prompt_id ? 'take:' + shot.source_prompt_id : shot.source_assembly_id ? 'assembly:' + shot.source_assembly_id : '';
}
function chooseSource(shot: ScreenplayShot, event: Event) {
    const value = (event.target as HTMLSelectElement).value;
    const [kind, id] = value.split(':');
    shot.source_prompt_id = kind === 'take' ? Number(id) : null;
    shot.source_assembly_id = kind === 'assembly' ? Number(id) : null;
}

function error(field: string): string | undefined {
    return props.errors['script.scenes.' + props.sceneIndex + '.shots.' + props.shotIndex + '.' + field];
}
</script>

<template>
    <div class="space-y-4">
        <ShotDirectionEditor v-model="shot" :speakers="speakers" />
        <div class="grid gap-2">
            <Label :for="'shot-action-' + sceneIndex + '-' + shotIndex">Что происходит в кадре</Label
            ><textarea
                :id="'shot-action-' + sceneIndex + '-' + shotIndex"
                v-model="shot.action"
                required
                rows="3"
                maxlength="5000"
                class="border-input bg-background focus-visible:ring-ring/50 resize-y rounded-md border p-3 text-sm outline-none focus-visible:ring-2"
            /><InputError :message="error('action')" />
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label :for="'shot-duration-' + sceneIndex + '-' + shotIndex">Длительность, с</Label
                ><Input
                    :id="'shot-duration-' + sceneIndex + '-' + shotIndex"
                    :model-value="shot.duration_ms / 1000"
                    type="number"
                    min="4"
                    max="30"
                    step="1"
                    required
                    @update:model-value="shot.duration_ms = Number($event) * 1000"
                /><InputError
                    :message="
                        shot.duration_ms < 4000 || shot.duration_ms > 30000 || shot.duration_ms % 1000 !== 0
                            ? 'Укажите целое число от 4 до 30 секунд.'
                            : error('duration_ms')
                    "
                />
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label :for="'shot-camera-' + sceneIndex + '-' + shotIndex">Камера и план</Label
                ><Input
                    :id="'shot-camera-' + sceneIndex + '-' + shotIndex"
                    v-model="shot.camera"
                    maxlength="2000"
                    placeholder="Например, средний план с плавным приближением"
                /><InputError :message="error('camera')" />
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="'shot-transition-' + sceneIndex + '-' + shotIndex">Начало кадра</Label
                ><select :id="'shot-transition-' + sceneIndex + '-' + shotIndex" v-model="shot.transition_mode" class="list-select w-full">
                    <option v-for="(label, value) in transitions" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <div v-if="shot.transition_mode !== 'new_scene'" class="grid gap-2">
                <Label :for="'shot-source-' + sceneIndex + '-' + shotIndex">Источник продолжения</Label
                ><select
                    :id="'shot-source-' + sceneIndex + '-' + shotIndex"
                    :value="sourceValue(shot)"
                    class="list-select w-full"
                    @change="chooseSource(shot, $event)"
                >
                    <option value="">Предыдущий принятый эпизод или выбранный кадр</option>
                    <option v-for="source in sources" :key="source.kind + ':' + source.id" :value="source.kind + ':' + source.id">
                        {{ source.label }}
                    </option>
                </select>
            </div>
        </div>
        <div v-if="shot.transition_mode !== 'new_scene'" class="grid gap-2 sm:max-w-xs">
            <Label :for="'shot-point-' + sceneIndex + '-' + shotIndex">Начало продолжения, с</Label
            ><Input
                :id="'shot-point-' + sceneIndex + '-' + shotIndex"
                :model-value="(shot.source_time_ms ?? 0) / 1000"
                type="number"
                min="0"
                step="0.1"
                @update:model-value="shot.source_time_ms = Number($event) * 1000"
            />
            <p class="text-muted-foreground text-xs">При значении 0 продолжение начнётся с конца видео.</p>
        </div>
        <fieldset class="space-y-3">
            <legend class="text-sm font-medium">Диалог героя</legend>
            <div
                v-for="(line, lineIndex) in shot.dialogue"
                :key="lineIndex"
                class="grid items-end gap-3 sm:grid-cols-[minmax(8rem,1fr)_minmax(0,3fr)_auto]"
                :class="{ 'dialogue-focus': recoveringLine === lineId(lineIndex) }"
            >
                <h2 v-if="recoveringLine === lineId(lineIndex)" class="text-xl font-bold">Диалог героя</h2>
                <div
                    v-if="recoveringLine === lineId(lineIndex)"
                    role="status"
                    class="bg-muted flex items-start gap-3 rounded-md border-l-2 p-3 sm:col-span-3"
                    :class="connected ? 'border-primary' : 'border-amber-500'"
                >
                    <component :is="connected ? CircleCheck : CircleAlert" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                    <div>
                        <strong :class="connected ? 'text-primary' : 'text-amber-700 dark:text-amber-300'">{{
                            connected ? 'Связь восстановлена' : 'Связь потеряна'
                        }}</strong>
                        <p class="text-muted-foreground text-sm">
                            {{ connectionMessage }}
                        </p>
                    </div>
                </div>
                <div v-show="recoveringLine !== lineId(lineIndex)" class="grid gap-2">
                    <Label :for="'speaker-' + sceneIndex + '-' + shotIndex + '-' + lineIndex">Персонаж</Label
                    ><select
                        :id="'speaker-' + sceneIndex + '-' + shotIndex + '-' + lineIndex"
                        v-model="line.character_id"
                        required
                        class="list-select w-full"
                    >
                        <option v-for="speaker in speakers" :key="speaker.id" :value="speaker.character_id">
                            {{ speaker.character_name ?? 'Персонаж' }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label :for="lineId(lineIndex)"
                        >Реплика · {{ speakers.find((speaker) => speaker.character_id === line.character_id)?.character_name ?? 'Персонаж' }}</Label
                    ><Input :id="lineId(lineIndex)" v-model="line.text" maxlength="2000" />
                </div>
                <Button
                    v-show="recoveringLine !== lineId(lineIndex)"
                    type="button"
                    variant="outline"
                    class="h-11"
                    @click="shot.dialogue.splice(lineIndex, 1)"
                    >Удалить реплику</Button
                >
                <Button v-if="recoveringLine === lineId(lineIndex)" type="submit" :disabled="!connected" class="h-11 justify-self-start sm:col-span-3"
                    >Сохранить на сервере</Button
                >
            </div>
            <Button
                type="button"
                variant="outline"
                class="h-11"
                :disabled="!speakers.length || shot.dialogue.length >= 20"
                @click="shot.dialogue.push({ character_id: speakers[0].character_id, text: '' })"
                ><Plus class="size-4" aria-hidden="true" />Добавить реплику</Button
            >
        </fieldset>
    </div>
</template>
