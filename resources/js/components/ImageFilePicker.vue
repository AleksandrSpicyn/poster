<script setup lang="ts">
import { ImagePlus } from '@lucide/vue';
import { useObjectUrl } from '@vueuse/core';
import { ref } from 'vue';

const props = defineProps<{ label: string; file: File | null; disabled?: boolean; required?: boolean }>();
const emit = defineEmits<{ select: [file: File] }>();
const dragging = ref(false);
const error = ref('');
const preview = useObjectUrl(() => props.file);

function select(files?: FileList | null) {
    dragging.value = false;
    if (props.disabled || !files?.length) return;
    const file = files[0];
    if (files.length !== 1 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
        error.value = 'Выберите одно изображение JPG, PNG или WebP размером до 10 МБ.';
        return;
    }
    error.value = '';
    emit('select', file);
}
</script>

<template>
    <div class="grid gap-2">
        <label
            class="image-dropzone"
            :class="{ dragging }"
            @dragover.prevent="dragging = !disabled"
            @dragleave.prevent="dragging = false"
            @drop.prevent="select($event.dataTransfer?.files)"
        >
            <ImagePlus class="size-6" aria-hidden="true" />
            <strong>{{ label }}</strong>
            <span>Перетащите JPG, PNG или WebP · до 10 МБ</span>
            <span class="file-action">{{ file ? 'Заменить файл' : 'Выбрать файл' }}</span>
            <input
                type="file"
                class="sr-only"
                :aria-label="label"
                accept="image/jpeg,image/png,image/webp"
                :disabled="disabled"
                :required="required && !file"
                @change="
                    select(($event.target as HTMLInputElement).files);
                    ($event.target as HTMLInputElement).value = '';
                "
            />
        </label>
        <div v-if="file" role="status" class="flex min-w-0 items-center gap-3">
            <img :src="preview" alt="" class="size-14 shrink-0 rounded-lg object-cover" />
            <div class="min-w-0">
                <p class="break-all">{{ file.name }}</p>
                <small>{{ file.type.split('/')[1].toUpperCase() }} · {{ Math.ceil(file.size / 1024).toLocaleString('ru') }} КБ</small>
            </div>
        </div>
        <p v-if="error" class="text-destructive text-sm" role="alert">{{ error }}</p>
    </div>
</template>
