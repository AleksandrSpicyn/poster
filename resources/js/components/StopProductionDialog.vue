<script setup lang="ts">
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import { Square } from '@lucide/vue';

defineProps<{ stage: string; recipients?: string; processing: boolean; error: string }>();
defineEmits<{ confirm: [] }>();
</script>

<template>
    <ConfirmationDialog
        heading="Остановка производства"
        title="Остановить производство?"
        description="Новые этапы не начнутся. Уже отправленный дубль может завершиться у провайдера."
        action="Остановить этапы"
        cancel="Продолжить производство"
        destructive
        :processing="processing"
        :error="error"
        @confirm="$emit('confirm')"
    >
        <template #trigger>
            <Button type="button" variant="outline" :class="{ 'h-11': recipients === undefined }">
                <Square v-if="recipients === undefined" class="size-4" aria-hidden="true" />Остановить следующие этапы
            </Button>
        </template>
        <div class="studio-row">
            <dt>Текущий этап</dt>
            <dd>{{ stage }}</dd>
        </div>
        <div v-if="recipients !== undefined" class="studio-row">
            <dt>Каналы выпуска</dt>
            <dd class="min-w-0 wrap-anywhere">{{ recipients }}</dd>
        </div>
    </ConfirmationDialog>
</template>
