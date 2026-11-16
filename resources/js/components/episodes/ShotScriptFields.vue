<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ContinuationSource, ScreenplayShot } from '@/types/episodes';
import type { CharacterRevision } from '@/types/series';
import { Plus } from '@lucide/vue';

const shot = defineModel<ScreenplayShot>({ required: true });
const props = defineProps<{
    sceneIndex: number;
    shotIndex: number;
    transitions: Record<string, string>;
    sources: ContinuationSource[];
    speakers: (CharacterRevision & { character_name?: string })[];
    errors: Record<string, string | undefined>;
}>();
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
        <div class="grid gap-2">
            <Label :for="'shot-action-' + sceneIndex + '-' + shotIndex">What happens on screen</Label
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
                <Label :for="'shot-duration-' + sceneIndex + '-' + shotIndex">Duration in seconds</Label
                ><Input
                    :id="'shot-duration-' + sceneIndex + '-' + shotIndex"
                    :model-value="shot.duration_ms / 1000"
                    type="number"
                    min="4"
                    max="30"
                    step="1"
                    required
                    @update:model-value="shot.duration_ms = Number($event) * 1000"
                /><InputError :message="error('duration_ms')" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label :for="'shot-camera-' + sceneIndex + '-' + shotIndex">Camera and framing</Label
                ><Input
                    :id="'shot-camera-' + sceneIndex + '-' + shotIndex"
                    v-model="shot.camera"
                    maxlength="2000"
                    placeholder="For example, medium shot with a slow push in"
                /><InputError :message="error('camera')" />
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="'shot-transition-' + sceneIndex + '-' + shotIndex">How this shot begins</Label
                ><select
                    :id="'shot-transition-' + sceneIndex + '-' + shotIndex"
                    v-model="shot.transition_mode"
                    class="border-input bg-background h-11 rounded-md border px-3 text-sm"
                >
                    <option v-for="(label, value) in transitions" :key="value" :value="value">{{ label }}</option>
                </select>
            </div>
            <div v-if="shot.transition_mode !== 'new_scene'" class="grid gap-2">
                <Label :for="'shot-source-' + sceneIndex + '-' + shotIndex">Continue from</Label
                ><select
                    :id="'shot-source-' + sceneIndex + '-' + shotIndex"
                    :value="sourceValue(shot)"
                    class="border-input bg-background h-11 rounded-md border px-3 text-sm"
                    @change="chooseSource(shot, $event)"
                >
                    <option value="">Previous accepted episode or chosen preceding shot</option>
                    <option v-for="source in sources" :key="source.kind + ':' + source.id" :value="source.kind + ':' + source.id">
                        {{ source.label }}
                    </option>
                </select>
            </div>
        </div>
        <div v-if="shot.transition_mode !== 'new_scene'" class="grid gap-2 sm:max-w-xs">
            <Label :for="'shot-point-' + sceneIndex + '-' + shotIndex">Continuation point in seconds</Label
            ><Input
                :id="'shot-point-' + sceneIndex + '-' + shotIndex"
                :model-value="(shot.source_time_ms ?? 0) / 1000"
                type="number"
                min="0"
                step="0.1"
                @update:model-value="shot.source_time_ms = Number($event) * 1000"
            />
            <p class="text-muted-foreground text-xs">0 uses the end of the selected video.</p>
        </div>
        <fieldset class="space-y-3">
            <legend class="text-sm font-medium">Dialogue</legend>
            <div
                v-for="(line, lineIndex) in shot.dialogue"
                :key="lineIndex"
                class="grid items-end gap-3 sm:grid-cols-[minmax(8rem,1fr)_minmax(0,3fr)_auto]"
            >
                <div class="grid gap-2">
                    <Label :for="'speaker-' + sceneIndex + '-' + shotIndex + '-' + lineIndex">Speaker</Label
                    ><select
                        :id="'speaker-' + sceneIndex + '-' + shotIndex + '-' + lineIndex"
                        v-model="line.character_id"
                        required
                        class="border-input bg-background h-11 rounded-md border px-3 text-sm"
                    >
                        <option v-for="speaker in speakers" :key="speaker.id" :value="speaker.character_id">
                            {{ speaker.character_name ?? 'Character' }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label :for="'dialogue-' + sceneIndex + '-' + shotIndex + '-' + lineIndex">Line</Label
                    ><Input :id="'dialogue-' + sceneIndex + '-' + shotIndex + '-' + lineIndex" v-model="line.text" maxlength="2000" />
                </div>
                <Button type="button" variant="outline" class="h-11" @click="shot.dialogue.splice(lineIndex, 1)">Remove line</Button>
            </div>
            <Button
                type="button"
                variant="outline"
                class="h-11"
                :disabled="!speakers.length || shot.dialogue.length >= 20"
                @click="shot.dialogue.push({ character_id: speakers[0].character_id, text: '' })"
                ><Plus class="size-4" aria-hidden="true" />Add line</Button
            >
        </fieldset>
    </div>
</template>
