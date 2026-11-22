<script setup lang="ts">
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import type { ManagedChannel } from '@/types/channels';
import { Unplug } from '@lucide/vue';

defineProps<{ channel: ManagedChannel; processing: boolean; error?: string; label: string }>();
defineEmits<{ confirm: [] }>();
</script>
<template>
    <ConfirmationDialog
        heading="Отзыв доступа к площадке"
        :title="'Отключить аккаунт ' + channel.type_label + '?'"
        description="Poster перестанет отправлять видео от имени этого аккаунта."
        action="Отключить аккаунт"
        destructive
        :processing="processing"
        :error="error"
        @confirm="$emit('confirm')"
    >
        <template #trigger>
            <Button variant="outline" class="min-h-10" :disabled="processing"> <Unplug class="size-4" aria-hidden="true" />{{ label }} </Button>
        </template>
        <div class="studio-row">
            <dt>Канал Poster</dt>
            <dd>{{ channel.name }}</dd>
        </div>
        <div class="studio-row">
            <dt>После отключения</dt>
            <dd>Для отправки потребуется авторизоваться снова</dd>
        </div>
    </ConfirmationDialog>
</template>
