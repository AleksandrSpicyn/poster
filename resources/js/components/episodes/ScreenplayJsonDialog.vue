<script setup lang="ts">
import type { Screenplay } from '@/types/episodes';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { useObjectUrl } from '@vueuse/core';
import { ref, watch } from 'vue';

const props = defineProps<{
    episodeId: number;
    screenplay: Screenplay;
    jsonError: string;
    saveError: string;
    draftStored: boolean;
    readOnly: boolean;
    processing: boolean;
    disabled: boolean;
    checkJson: () => Record<string, unknown> | null;
}>();
const emit = defineEmits<{ import: [] }>();
const scriptText = defineModel<string>({ required: true });
const importOpen = ref(false);
const checkedJson = ref(false);
const screenplayDownload = useObjectUrl(() =>
    importOpen.value ? new Blob([JSON.stringify(props.screenplay, null, 2)], { type: 'application/json' }) : null,
);
watch(scriptText, () => {
    checkedJson.value = false;
});
</script>

<template>
    <Dialog v-model:open="importOpen">
        <DialogTrigger as-child>
            <Button variant="outline" class="h-auto max-w-full whitespace-normal">Импорт и экспорт сценария в JSON</Button>
        </DialogTrigger>
        <DialogContent class="studio-confirm screenplay-json-dialog" :show-close="false">
            <DialogTitle>Импорт и экспорт сценария</DialogTitle>
            <DialogDescription class="sr-only">Проверка и импорт сценария в формате JSON</DialogDescription>
            <div class="grid gap-2">
                <Label for="screenplay-json">Фрагмент JSON</Label>
                <textarea
                    id="screenplay-json"
                    v-model="scriptText"
                    :aria-invalid="!!jsonError"
                    :aria-describedby="jsonError ? 'screenplay-json-error' : undefined"
                    rows="5"
                    spellcheck="false"
                    :disabled="readOnly || processing"
                    class="w-full rounded-lg border p-3 font-mono text-sm"
                />
            </div>
            <div v-if="jsonError" id="screenplay-json-error" role="alert" class="studio-confirm-notice">
                <strong>Неверный формат</strong>
                <p>{{ jsonError }}</p>
            </div>
            <div v-if="jsonError && draftStored" class="studio-confirm-notice">
                <strong>Черновик сохранён</strong>
                <p>Исправьте поле и проверьте файл повторно.</p>
            </div>
            <p v-if="checkedJson" role="status">Структура сценария проверена.</p>
            <InputError :message="saveError" />
            <slot />
            <div class="json-actions">
                <Button variant="outline" @click="checkedJson = !!checkJson()">Проверить JSON</Button>
                <Button as-child variant="outline">
                    <a :href="screenplayDownload" :download="`episode-${episodeId}.json`">Скачать черновик в JSON</a>
                </Button>
                <Button v-if="checkedJson" :disabled="disabled || processing" @click="emit('import')">Импортировать новую версию</Button>
                <DialogClose as-child><Button variant="outline">Закрыть</Button></DialogClose>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
:global(.screenplay-json-dialog[data-slot='dialog-content']) {
    gap: 16px;
}
:global(.screenplay-json-dialog > [data-slot='dialog-title']) {
    margin: 0;
}
.json-actions {
    grid-auto-rows: 1fr;
    display: grid;
    gap: 12px;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 210px), 1fr));
}
.json-actions :deep([data-slot='button']) {
    width: 100%;
    min-height: 48px;
    height: auto;
    white-space: normal;
}
</style>
