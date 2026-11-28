<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CloudUpload, Plus, X, File } from '@lucide/vue';
import { useForm } from '@inertiajs/vue3';
import { useFormApi } from '@/lib/api/useFormApi';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { assetKinds, type SeriesAsset } from '@/types/series';

const props = defineProps<{ seriesId: number }>();
const emit = defineEmits<{ saved: [] }>();
const submit = useFormApi();
const form = useForm({ label: '', kind: 'location' as SeriesAsset['kind'], file: null as globalThis.File | null });
const dialog = ref<HTMLDialogElement>();
const input = ref<HTMLInputElement>();
const thumbnail = ref('');
const dragging = ref(false);
const notice = ref('');
const accept = computed(
    () =>
        ({
            sound: '.mp3,.wav',
            season: '.json',
            insert: '.jpg,.jpeg,.png,.webp,.mp4,.webm',
            location: '.jpg,.jpeg,.png,.webp',
            ensemble: '.jpg,.jpeg,.png,.webp',
            prop: '.jpg,.jpeg,.png,.webp',
        })[form.kind],
);
const hint = computed(() =>
    form.kind === 'season'
        ? 'JSON poster.season.v1, 1–100 эпизодов'
        : form.kind === 'sound'
          ? 'WAV или MP3, от 10 мс до 30 секунд'
          : 'PNG, JPEG или WebP, 64–6000 пикселей по каждой стороне, до 20 Мп' +
            (form.kind === 'insert' ? '. Также MP4 или WebM с видеодорожкой' : ''),
);
watch(
    () => form.file,
    (file, _previous, cleanup) => {
        thumbnail.value = '';
        if (!file || !(file.type.startsWith('image/') || file.type.startsWith('video/'))) return;
        const url = URL.createObjectURL(file);
        thumbnail.value = url;
        cleanup(() => URL.revokeObjectURL(url));
    },
);
function choose(file?: globalThis.File) {
    if (!file || form.processing) return;
    form.file = file;
    if (!form.label.trim()) form.label = file.name.replace(/\.[^.]+$/, '').slice(0, 150);
    form.clearErrors('file');
}
function open(file?: globalThis.File) {
    dragging.value = false;
    notice.value = '';
    if (file) {
        if (file.type.startsWith('video/')) form.kind = 'insert';
        else if (file.type.startsWith('audio/')) form.kind = 'sound';
        else if (file.name.toLowerCase().endsWith('.json')) form.kind = 'season';
        choose(file);
    }
    dialog.value?.showModal();
}
function reset() {
    form.reset();
    form.clearErrors();
    if (input.value) input.value.value = '';
}
function save() {
    if (form.file && form.file.size > 10 * 1024 * 1024) {
        form.setError('file', 'Максимальный размер файла — 10 МиБ.');
        return;
    }
    submit(form, ['series.assets.store', { series: props.seriesId }], {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            dialog.value?.close();
            notice.value = 'Материал добавлен.';
            emit('saved');
        },
    });
}
</script>
<template>
    <div>
        <div
            class="bg-card border-input flex flex-col justify-between gap-4 rounded-xl border border-dashed p-4 min-[700px]:flex-row min-[700px]:items-center min-[1100px]:px-6 min-[1100px]:py-5"
            :class="{ 'ring-ring ring-2': dragging }"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="open($event.dataTransfer?.files[0])"
        >
            <div class="flex items-center gap-4">
                <span class="bg-accent text-accent-foreground grid size-11 shrink-0 place-items-center rounded-xl"><CloudUpload /></span>
                <div>
                    <h2 class="text-lg font-bold">Добавить материал</h2>
                    <p class="text-muted-foreground text-sm">Перетащите файл сюда или выберите его на устройстве.</p>
                </div>
            </div>
            <Button class="min-h-11" @click="open()"><Plus /> Добавить файл</Button>
        </div>
        <p v-if="notice" class="text-accent-foreground mt-3 text-sm" role="status">{{ notice }}</p>
        <dialog
            ref="dialog"
            aria-labelledby="upload-title"
            class="bg-card text-foreground fixed inset-0 m-auto max-h-[90dvh] w-[min(680px,calc(100%-2rem))] rounded-xl border backdrop:bg-black/85"
            @close="reset"
            @cancel="form.processing && $event.preventDefault()"
        >
            <header class="flex items-center justify-between border-b px-5 py-3">
                <h2 id="upload-title" class="text-xl font-bold">Новый материал</h2>
                <Button variant="outline" size="icon" :disabled="form.processing" aria-label="Закрыть загрузку" @click="dialog?.close()"
                    ><X
                /></Button>
            </header>
            <form class="grid gap-4 p-5" @submit.prevent="save">
                <fieldset class="grid gap-4" :disabled="form.processing">
                    <div class="grid gap-3 min-[700px]:grid-cols-[1fr_1.4fr]">
                        <label class="grid gap-2 text-sm"
                            >Вид материала<select v-model="form.kind" class="bg-background border-input h-12 rounded-lg border px-3">
                                <option v-for="(name, kind) in assetKinds" :key="kind" :value="kind">{{ name }}</option>
                            </select></label
                        >
                        <label class="grid gap-2 text-sm"
                            >Название<Input v-model="form.label" class="h-12" required maxlength="150" placeholder="Как называется материал?"
                        /></label>
                    </div>
                    <div
                        v-show="!form.file"
                        class="bg-muted/50 grid justify-items-center gap-2 rounded-xl border border-dashed p-5 text-center"
                        @dragover.prevent
                        @drop.prevent="choose($event.dataTransfer?.files[0])"
                    >
                        <CloudUpload /><strong>Перетащите файл сюда</strong>
                        <p class="text-muted-foreground text-sm">{{ hint }}. До 10 МиБ.</p>
                        <Button type="button" variant="outline" @click="input?.click()">Выбрать файл</Button>
                        <input
                            ref="input"
                            type="file"
                            hidden
                            :accept="accept"
                            aria-label="Файл материала"
                            @change="
                                choose(input?.files?.[0]);
                                if (input) input.value = '';
                            "
                        />
                    </div>
                    <div v-if="form.file" class="flex items-center gap-3 rounded-lg border p-3">
                        <video
                            v-if="thumbnail && form.file.type.startsWith('video/')"
                            :src="thumbnail"
                            muted
                            playsinline
                            preload="metadata"
                            class="size-14 rounded-md object-cover"
                        /><img v-else-if="thumbnail" :src="thumbnail" alt="" class="size-14 rounded-md object-cover" /><File v-else /><span
                            class="min-w-0 flex-1"
                            ><strong class="block wrap-anywhere">{{ form.file.name }}</strong
                            ><small>{{ Math.ceil(form.file.size / 1024) }} КиБ</small></span
                        ><Button type="button" variant="outline" size="icon" aria-label="Убрать файл" @click="form.file = null"><X /></Button>
                    </div>
                    <p v-if="form.hasErrors" class="text-destructive" role="alert">{{ Object.values(form.errors).join(' ') }}</p>
                    <progress v-if="form.processing" :value="form.progress?.percentage" max="100" aria-label="Прогресс загрузки" class="w-full" />
                    <div class="flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="outline" @click="dialog?.close()">Отмена</Button
                        ><Button type="submit" :disabled="!form.file || !form.label.trim() || form.processing">Сохранить материал</Button>
                    </div>
                </fieldset>
            </form>
        </dialog>
    </div>
</template>
