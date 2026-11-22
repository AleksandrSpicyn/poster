<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SeriesSpecification } from '@/types/series';

const specification = defineModel<SeriesSpecification & { story_outline: string }>('specification', { required: true });
const autoPublish = defineModel<boolean>('autoPublish', { required: true });
const automationEnabled = defineModel<boolean>('automationEnabled', { required: true });
const outputMode = defineModel<string>('outputMode', { required: true });
const episodeLimit = defineModel<number | string>('episodeLimit', { required: true });
defineProps<{
    section: string;
    processing: boolean;
    errors: Record<string, string | undefined>;
    audiences: Record<string, string>;
    audioModes: Record<string, string>;
    outputModes: Record<string, string>;
    cycleStructures: Record<string, string>;
    sceneModes: Record<string, string>;
}>();
const labels: Record<string, string> = {
    episodes: 'По эпизодам',
    single_video: 'Одно полное видео',
    continuous_story: 'Непрерывная история',
    anthology: 'Цикл завершённых историй',
    scripted: 'Сцены следуют сюжету',
    same_scene: 'Одна сцена во всех частях',
    general: 'Для всех',
    adult: 'Для взрослых',
    children: 'Для детей',
    native: 'Речь и звук',
    silent: 'Без звука',
};
</script>

<template>
    <div class="space-y-6">
        <div v-if="section === 'plan'" class="grid gap-2">
            <Label for="output-mode">Формат выпуска</Label>
            <select
                id="output-mode"
                v-model="outputMode"
                :disabled="processing"
                class="border-input bg-background h-11 rounded-md border px-3 text-sm"
            >
                <option v-for="(label, value) in outputModes" :key="value" :value="value">{{ labels[value] ?? label }}</option>
            </select>
            <InputError :message="errors.output_mode" />
            <p v-if="outputMode === 'single_video'" class="text-muted-foreground text-sm">
                Части собираются в одно видео. Для публикации примите полную сборку. Допустимо до 20 частей и 600 секунд.
            </p>
        </div>
        <div v-if="section === 'plan' && outputMode === 'single_video'" class="grid gap-2">
            <Label for="cycle-structure">Структура истории</Label>
            <select
                id="cycle-structure"
                v-model="specification.cycle_structure"
                :disabled="processing"
                class="border-input bg-background h-11 rounded-md border px-3 text-sm"
            >
                <option v-for="(label, value) in cycleStructures" :key="value" :value="value">{{ labels[value] ?? label }}</option>
            </select>
            <Label for="cycle-scenes">Связь сцен</Label>
            <select
                id="cycle-scenes"
                v-model="specification.scene_mode"
                :disabled="processing"
                class="border-input bg-background h-11 rounded-md border px-3 text-sm"
            >
                <option v-for="(label, value) in sceneModes" :key="value" :value="value">{{ labels[value] ?? label }}</option>
            </select>
            <p class="text-muted-foreground text-xs">Опишите переходы между сценами или непрерывный дубль в правилах повествования.</p>
        </div>
        <InputError :message="errors.series_lock_version" /><InputError :message="errors.series" />
        <div v-if="section === 'plan'" class="grid gap-2">
            <Label for="generation-duration">Длительность генерации, секунды</Label>
            <Input id="generation-duration" v-model="specification.shot_duration_seconds" type="number" min="4" max="30" :disabled="processing" />
            <InputError :message="errors['series_specification.shot_duration_seconds']" />
            <p class="text-muted-foreground text-xs">Для эпизода на 60 секунд требуется четыре генерации по 15 секунд.</p>
        </div>
        <div v-if="section === 'cast'" class="grid gap-5 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="series-style">Визуальный стиль</Label
                ><Input
                    id="series-style"
                    v-model="specification.visual_style"
                    maxlength="2000"
                    :disabled="processing"
                    placeholder="Например, акварельная анимация"
                /><InputError :message="errors['series_specification.visual_style']" />
            </div>
            <div class="grid gap-2">
                <Label for="series-language">Язык</Label
                ><Input id="series-language" v-model="specification.language" maxlength="35" :disabled="processing" placeholder="en" /><InputError
                    :message="errors['series_specification.language']"
                />
            </div>
            <div class="grid gap-2">
                <Label for="series-audience">Аудитория</Label
                ><select
                    id="series-audience"
                    v-model="specification.audience"
                    :disabled="processing"
                    class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
                >
                    <option v-for="(label, value) in audiences" :key="value" :value="value">{{ labels[value] ?? label }}</option></select
                ><InputError :message="errors['series_specification.audience']" />
            </div>
            <div class="grid gap-2">
                <Label for="series-duration">Длительность эпизода, секунды</Label
                ><Input
                    id="series-duration"
                    v-model="specification.episode_duration_seconds"
                    type="number"
                    min="4"
                    max="300"
                    :disabled="processing"
                /><InputError :message="errors['series_specification.episode_duration_seconds']" />
            </div>
        </div>
        <div v-if="section === 'cast'" class="grid gap-2">
            <Label for="series-audio">Звук</Label>
            <select
                id="series-audio"
                v-model="specification.audio_mode"
                :disabled="processing"
                class="border-input bg-background focus-visible:ring-ring/50 h-11 rounded-md border px-3 text-sm outline-none focus-visible:ring-2"
            >
                <option v-for="(label, value) in audioModes" :key="value" :value="value">{{ labels[value] ?? label }}</option>
            </select>
            <p class="text-muted-foreground text-xs">
                В режиме без звука сцены создаются без диалогов, а готовый монтаж сохраняется без аудиодорожки.
            </p>
            <InputError :message="errors['series_specification.audio_mode']" />
        </div>
        <div v-if="section === 'plan'" class="grid gap-2">
            <Label for="series-outline">Сюжет сериала</Label>
            <textarea
                id="series-outline"
                v-model="specification.story_outline"
                maxlength="20000"
                rows="8"
                :disabled="processing"
                class="border-input bg-background focus-visible:ring-ring/50 w-full resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                placeholder="Основная история, идеи эпизодов, развитие персонажей и развязка."
            />
            <InputError :message="errors['series_specification.story_outline']" />
        </div>
        <div v-if="section === 'plan'" class="grid gap-2">
            <Label for="series-rules">Правила повествования</Label
            ><textarea
                id="series-rules"
                v-model="specification.narrative_rules"
                maxlength="20000"
                rows="5"
                :disabled="processing"
                class="border-input bg-background focus-visible:ring-ring/50 w-full resize-y rounded-md border p-3 text-sm leading-relaxed outline-none focus-visible:ring-2"
                placeholder="Тон повествования, темп и завершение эпизодов."
            /><InputError :message="errors['series_specification.narrative_rules']" />
        </div>
        <fieldset v-if="section === 'plan'" class="grid gap-5 rounded-lg border p-4 sm:p-5">
            <legend class="px-2 text-sm font-semibold">Автоматизация</legend>
            <label class="flex min-h-11 cursor-pointer items-start gap-3">
                <input v-model="automationEnabled" type="checkbox" class="accent-primary mt-1 size-4 shrink-0" :disabled="processing" />
                <span class="text-sm"
                    ><span class="font-medium">Автоматически создавать эпизоды</span
                    ><span class="text-muted-foreground mt-1 block leading-relaxed"
                        >После включения начнётся работа над первым выпуском. Сначала понадобятся референсы персонажей. Следующие эпизоды создаются по
                        расписанию.</span
                    ></span
                >
            </label>
            <InputError :message="errors.automation_enabled" />
            <label class="flex min-h-11 cursor-pointer items-start gap-3">
                <input v-model="autoPublish" type="checkbox" class="accent-primary mt-1 size-4 shrink-0" :disabled="processing" />
                <span class="text-sm font-medium">Автоматически публиковать готовое видео</span>
            </label>
            <InputError :message="errors.auto_publish" />
            <div class="grid gap-2">
                <Label for="series-limit">Количество эпизодов</Label>
                <Input
                    id="series-limit"
                    v-model="episodeLimit"
                    type="number"
                    min="1"
                    max="1000"
                    placeholder="Без ограничения"
                    :disabled="processing"
                />
                <p class="text-muted-foreground text-xs">
                    Оставьте поле пустым для сериала без ограничения. После последнего запланированного эпизода производство остановится.
                </p>
                <InputError :message="errors.episode_limit" />
            </div>
        </fieldset>
    </div>
</template>
