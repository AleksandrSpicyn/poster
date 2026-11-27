<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useFormApi } from '@/lib/api/useFormApi';
import { useRoute } from '@/composables/useRoute';
import { generateVoicePreview } from '@/lib/api/characterVoice';
import type { CharacterRouteParams } from '@/types/series';
import { useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, shallowRef } from 'vue';

const props = defineProps<{
    params: CharacterRouteParams & { revision: number };
    voices: Record<string, string>;
    version: number;
    currentSample: number | null;
    disabled: boolean;
}>();
const route = useRoute();
const submit = useFormApi();
const voiceId = ref(Object.keys(props.voices)[0] ?? '');
const line = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const preview = shallowRef<{ url: string; label: string; file: File }>();
const form = useForm({ file: null as File | null, view: 'voice', label: '', lock_version: props.version, replace_voice_id: props.currentSample });
let request: AbortController | undefined;

function discard() {
    if (preview.value) URL.revokeObjectURL(preview.value.url);
    preview.value = undefined;
    form.clearErrors();
    error.value = '';
}

async function generate() {
    if (loading.value || saving.value || props.disabled || !line.value.trim() || !voiceId.value) return;
    loading.value = true;
    error.value = '';
    request = new AbortController();
    const signal = request.signal;
    const id = voiceId.value;
    try {
        const blob = await generateVoicePreview(
            route('characters.revisions.voice_preview', { ...props.params }),
            id,
            line.value.trim(),
            props.version,
            signal,
        );
        if (signal.aborted) return;
        discard();
        preview.value = {
            url: URL.createObjectURL(blob),
            file: new File([blob], id + '.wav', { type: 'audio/wav' }),
            label: 'Seed Speech · ' + props.voices[id],
        };
    } catch (failure) {
        if (!signal.aborted) error.value = failure instanceof Error ? failure.message : 'Не удалось получить пробу.';
    } finally {
        loading.value = false;
    }
}

function save() {
    if (!preview.value || props.disabled || loading.value || saving.value) return;
    saving.value = true;
    form.file = preview.value.file;
    form.label = preview.value.label;
    form.lock_version = props.version;
    error.value = '';
    submit(form, ['characters.references.store', { ...props.params }], {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false;
        },
        preserveState: 'errors',
        onHttpException: (response) => {
            if (response.status === 419) return;
            error.value = 'Не удалось подтвердить сохранение. Обновите карточку персонажа.';
            return false;
        },
        onNetworkError: () => {
            error.value = 'Соединение прервалось. Обновите карточку, чтобы проверить сохранение.';
            return false;
        },
    });
}

onBeforeUnmount(() => {
    request?.abort();
    discard();
});
</script>

<template>
    <section class="grid min-w-0 gap-3" :aria-labelledby="'voice-heading-' + params.revision">
        <h3 :id="'voice-heading-' + params.revision" class="text-sm font-semibold">Голосовая проба</h3>
        <p v-if="!Object.keys(voices).length" class="text-muted-foreground text-sm">
            Seed Speech не настроен. Можно загрузить готовый файл WAV или MP3.
        </p>
        <template v-else>
            <div class="grid min-w-0 gap-3 sm:grid-cols-[minmax(8rem,1fr)_2fr]">
                <div class="grid min-w-0 gap-2">
                    <Label :for="'voice-preset-' + params.revision">Голос BytePlus</Label>
                    <select
                        :id="'voice-preset-' + params.revision"
                        v-model="voiceId"
                        class="border-input bg-background h-11 rounded-md border px-3 text-sm"
                        :disabled="disabled || loading || saving"
                        @change="discard"
                    >
                        <option v-for="(name, id) in voices" :key="id" :value="id">{{ name }}</option>
                    </select>
                </div>
                <div class="grid min-w-0 gap-2">
                    <Label :for="'voice-line-' + params.revision">Пробная реплика</Label>
                    <Input
                        :id="'voice-line-' + params.revision"
                        v-model="line"
                        maxlength="200"
                        :aria-describedby="'voice-limit-' + params.revision"
                        class="h-11"
                        placeholder="Одна фраза из сценария"
                        :disabled="disabled || loading || saving"
                        @update:model-value="discard"
                    />
                    <p :id="'voice-limit-' + params.revision" class="text-muted-foreground text-xs">До 200 символов. Запись от 2 до 30 секунд.</p>
                </div>
            </div>
            <div>
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11"
                    :disabled="disabled || loading || saving || !line.trim()"
                    :aria-busy="loading"
                    @click="generate"
                >
                    {{ loading ? 'Генерируем пробу…' : 'Создать пробу' }}
                </Button>
            </div>
            <div v-if="preview" class="grid min-w-0 gap-2">
                <audio :key="preview.url" controls :src="preview.url" class="w-full" :aria-label="'Проба голоса ' + preview.label" />
                <p v-if="currentSample" class="text-muted-foreground text-sm">Этот дубль заменит сохранённый образец.</p>
                <div>
                    <Button type="button" class="min-h-11" :disabled="disabled || loading || saving" @click="save">{{
                        saving ? 'Сохраняем…' : 'Использовать этот дубль'
                    }}</Button>
                </div>
            </div>
            <div role="status" aria-live="polite"><InputError :message="error || Object.values(form.errors)[0]" /></div>
        </template>
    </section>
</template>
