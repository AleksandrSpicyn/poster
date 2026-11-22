<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    assembly: number;
    assemblyNumber: number;
    partCount: number;
    duration: number | null;
    videoUrl: string | null;
    number: number;
    next: number;
    time: number;
    version: number;
    editable: boolean;
    saved?: { notes: string; reviewed: boolean };
}>();
const emit = defineEmits<{ saved: [] }>();
const route = useRoute();
const form = useForm({ lock_version: props.version, notes: props.saved?.notes ?? '', reviewed: props.saved?.reviewed ?? false });
const errors = computed<Record<string, string | undefined>>(() => form.errors);
function seekFrame(event: Event, seconds: number) {
    const element = event.currentTarget;
    if (element instanceof HTMLVideoElement) element.currentTime = Math.max(0, seconds);
}
watch(
    () => props.saved,
    (saved) => {
        if (!form.isDirty && saved) {
            form.defaults({ lock_version: props.version, notes: saved.notes, reviewed: saved.reviewed });
            form.reset();
        }
    },
);
function save() {
    form.put(route('series_assemblies.boundaries.update', { assembly: props.assembly, boundary: props.number }), {
        preserveScroll: true,
        onSuccess: () => {
            form.lock_version = props.version;
            form.defaults();
            emit('saved');
        },
    });
}
</script>
<template>
    <section class="studio-panel">
        <h2>Проверка стыков</h2>
        <div v-if="videoUrl" class="grid grid-cols-2 gap-2">
            <figure
                v-for="frame in [
                    { part: number, at: time - 0.001 },
                    { part: next, at: time },
                ]"
                :key="frame.part"
            >
                <video
                    :src="videoUrl"
                    muted
                    preload="auto"
                    playsinline
                    :aria-label="'Кадр эпизода ' + frame.part"
                    class="aspect-[2.3] w-full rounded-lg bg-black object-cover"
                    @loadedmetadata="seekFrame($event, frame.at)"
                />
                <figcaption class="text-muted-foreground text-sm">
                    {{ frame.part }} · {{ frame.at.toLocaleString('ru', { minimumFractionDigits: 3, maximumFractionDigits: 3 }) }} с
                </figcaption>
            </figure>
        </div>
        <div class="studio-row">
            <span>Сборка</span><span>Версия {{ assemblyNumber }} · Эпизодов: {{ partCount }} · {{ (duration ?? 0) / 1000 }} с</span>
        </div>
        <form :id="'boundary-' + number" class="grid gap-3" @submit.prevent="save">
            <Label :for="'boundary-notes-' + number">Заметка по стыку</Label>
            <textarea
                :id="'boundary-notes-' + number"
                v-model="form.notes"
                maxlength="2000"
                :readonly="!editable"
                class="border-input bg-background min-h-20 rounded-lg border p-3"
            />
            <InputError :message="form.errors.notes" />
            <label class="flex items-center gap-2"><input v-model="form.reviewed" type="checkbox" :disabled="!editable" />Стык проверен</label>
            <p class="text-muted-foreground text-sm">Граница {{ time.toLocaleString('ru') }} с</p>
            <InputError :message="errors.review || form.errors.lock_version" />
            <p v-if="form.recentlySuccessful" role="status">Проверка сохранена.</p>
            <Button v-if="editable" type="submit" class="justify-self-start" :disabled="form.processing">Сохранить проверку</Button>
        </form>
    </section>
</template>
