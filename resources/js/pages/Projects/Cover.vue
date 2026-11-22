<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProjectSummary } from '@/types/projects';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref } from 'vue';
import { useRoute } from 'ziggy-js';

const { project } = defineProps<{ project: ProjectSummary }>();
const route = useRoute();
const form = useForm({ file: null as File | null, base_id: project.cover?.id ?? null, x: project.cover?.x ?? 50, y: project.cover?.y ?? 50 });
const localImage = ref('');
const ratio = ref(1);
const source = computed(() => localImage.value || project.cover?.url);
const cropWidth = computed(() => Math.min(100, (100 * (16 / 9)) / ratio.value));
const cropHeight = computed(() => Math.min(100, (100 * ratio.value) / (16 / 9)));
const position = computed(() => form.x + '% ' + form.y + '%');

function releasePreview() {
    if (localImage.value) URL.revokeObjectURL(localImage.value);
    localImage.value = '';
}
onUnmounted(releasePreview);
function chooseFile(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    releasePreview();
    form.file = file;
    localImage.value = URL.createObjectURL(file);
    form.x = form.y = 50;
    form.clearErrors();
}
function measure(event: Event) {
    const image = event.target as HTMLImageElement;
    ratio.value = image.naturalWidth / image.naturalHeight;
}
function clamp(value: number) {
    return Math.round(Math.max(0, Math.min(100, value)));
}
function move(event: PointerEvent) {
    if (event.type === 'pointermove' && !event.buttons) return;
    const target = event.currentTarget as HTMLElement;
    if (event.type === 'pointerdown') target.setPointerCapture(event.pointerId);
    const area = target.getBoundingClientRect();
    if (cropWidth.value < 100)
        form.x = clamp(((((event.clientX - area.left) / area.width) * 100 - cropWidth.value / 2) / (100 - cropWidth.value)) * 100);
    if (cropHeight.value < 100)
        form.y = clamp(((((event.clientY - area.top) / area.height) * 100 - cropHeight.value / 2) / (100 - cropHeight.value)) * 100);
}
function save() {
    form.post(route('projects.cover.store', project.id), { preserveState: 'errors', forceFormData: true });
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Мои проекты', href: route('projects.index') },
            { title: project.name, href: route('projects.edit', project.id) },
        ]"
    >
        <Head title="Обложка проекта" />
        <div class="workspace-page">
            <Heading title="Обложка проекта" description="Кадрирование" />
            <form class="studio-panel space-y-5" @submit.prevent="save">
                <h2>Кадрирование</h2>
                <div v-if="source" class="flex items-start gap-4">
                    <button
                        type="button"
                        class="relative max-w-[25%] touch-none overflow-hidden rounded"
                        aria-label="Точка интереса"
                        @pointerdown="move"
                        @pointermove="move"
                        @keydown.left.prevent="form.x = clamp(form.x - 1)"
                        @keydown.right.prevent="form.x = clamp(form.x + 1)"
                        @keydown.up.prevent="form.y = clamp(form.y - 1)"
                        @keydown.down.prevent="form.y = clamp(form.y + 1)"
                    >
                        <img :src="source" alt="" draggable="false" class="block max-h-56" @load="measure" />
                        <span
                            aria-hidden="true"
                            class="border-primary pointer-events-none absolute border-2"
                            :style="{
                                width: cropWidth + '%',
                                height: cropHeight + '%',
                                left: ((100 - cropWidth) * form.x) / 100 + '%',
                                top: ((100 - cropHeight) * form.y) / 100 + '%',
                            }"
                        ></span>
                    </button>
                    <figure class="w-full max-w-[400px]">
                        <img
                            :src="source"
                            alt="Обложка проекта"
                            class="aspect-video w-full rounded object-cover"
                            :style="{ objectPosition: position }"
                        />
                        <figcaption class="text-muted-foreground mt-1 text-sm">Карточка 16:9</figcaption>
                    </figure>
                </div>
                <label class="block"
                    >Изображение<input type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block max-w-full" @change="chooseFile"
                /></label>
                <InputError :message="form.errors.file ?? form.errors.x ?? form.errors.y ?? form.errors.base_id" />
                <Button type="submit" :disabled="form.processing || !source">Применить кадрирование</Button>
            </form>
        </div>
    </AppLayout>
</template>
