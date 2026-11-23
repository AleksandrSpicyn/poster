<script setup lang="ts">
import SeriesSettingsTabs from '@/components/projects/SeriesSettingsTabs.vue';
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
            { title: project.name, href: route('projects.show', project.id) },
            { title: project.content_mode === 'series' ? 'Паспорт сериала' : 'Настройки проекта', href: route('projects.edit', project.id) },
            { title: 'Обложка проекта', href: route('projects.cover.edit', project.id) },
        ]"
    >
        <Head title="Обложка проекта" />
        <div class="workspace-page cover-editor">
            <Heading title="Обложка проекта" description="Кадрирование" />
            <SeriesSettingsTabs v-if="project.content_mode === 'series'" :project-id="project.id" section="cover" />
            <form class="studio-panel crop-panel" @submit.prevent="save">
                <h2>Кадрирование</h2>
                <div v-if="source" class="crop-images">
                    <button
                        type="button"
                        class="crop-source"
                        aria-label="Переместить рамку кадрирования стрелками"
                        @pointerdown="move"
                        @pointermove="move"
                        @keydown.left.prevent="form.x = clamp(form.x - 1)"
                        @keydown.right.prevent="form.x = clamp(form.x + 1)"
                        @keydown.up.prevent="form.y = clamp(form.y - 1)"
                        @keydown.down.prevent="form.y = clamp(form.y + 1)"
                    >
                        <img :src="source" alt="" draggable="false" class="source-image" @load="measure" />
                        <span
                            aria-hidden="true"
                            class="crop-frame"
                            :style="{
                                width: cropWidth + '%',
                                height: cropHeight + '%',
                                left: ((100 - cropWidth) * form.x) / 100 + '%',
                                top: ((100 - cropHeight) * form.y) / 100 + '%',
                            }"
                        ></span>
                    </button>
                    <figure class="crop-preview">
                        <img :src="source" alt="Обложка проекта" class="aspect-video w-full object-cover" :style="{ objectPosition: position }" />
                        <figcaption>Карточка 16:9</figcaption>
                    </figure>
                </div>
                <label class="mb-4 block text-sm">
                    Изображение<input type="file" accept="image/png,image/jpeg,image/webp" class="mt-2 block max-w-full" @change="chooseFile" />
                </label>
                <InputError :message="form.errors.file ?? form.errors.x ?? form.errors.y ?? form.errors.base_id" />
                <Button type="submit" :disabled="form.processing || !source">Применить кадрирование</Button>
            </form>
        </div>
    </AppLayout>
</template>

<style scoped>
.cover-editor {
    gap: 14px;
}
.crop-panel {
    padding: 16px 23px 29px;
}
.crop-panel h2 {
    font-size: 20px;
    font-weight: 700;
    line-height: 28px;
    margin-bottom: 19px;
}
.crop-images {
    display: flex;
    align-items: flex-start;
    gap: 15px;
}
.crop-source {
    position: relative;
    flex: none;
    max-width: 118px;
    overflow: hidden;
    touch-action: none;
    cursor: crosshair;
}
.source-image {
    display: block;
    max-height: 208px;
    max-width: 100%;
}
.crop-frame {
    position: absolute;
    border: 3px solid var(--primary);
    box-shadow: 0 0 0 250px rgb(0 0 0 / 55%);
    pointer-events: none;
}
.crop-preview {
    width: min(400px, 100%);
    min-width: 0;
}
.crop-preview figcaption {
    min-height: 28px;
    padding-top: 4px;
    color: var(--muted-foreground);
    font-size: 13px;
}
.crop-source:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
.crop-panel button[type='submit'] {
    padding-inline: 15px;
    min-height: 44px;
    font-weight: 700;
}
@media (max-width: 767px) {
    .cover-editor {
        gap: 21px;
    }
    .crop-preview {
        margin-right: 3px;
    }
    .crop-panel {
        padding: 8px 15px 20px;
    }
}
</style>
