<script setup lang="ts">
import { useFormApi } from '@/lib/api/useFormApi';
import ConfirmationDialog from '@/components/ConfirmationDialog.vue';
import { Button } from '@/components/ui/button';
import type { ProjectPageProps } from '@/types/projects';
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{ project: Pick<ProjectPageProps['project'], 'id' | 'name' | 'content_mode' | 'can_delete' | 'blocking_runs_count'> }>();
const emit = defineEmits<{ deleted: [] }>();
const submitForm = useFormApi();
const form = useForm({});
const error = ref('');
const disabled = computed(() => !props.project.can_delete || !!props.project.blocking_runs_count || form.processing);

function destroy() {
    if (disabled.value) return;
    error.value = '';
    submitForm(form, ['projects.destroy', props.project.id], {
        onSuccess: () => emit('deleted'),
        onError: (errors) => {
            error.value = Object.values(errors).join(' ');
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            error.value = 'Не удалось удалить проект. Обновите страницу и повторите попытку.';
            return false;
        },
        onNetworkError: () => {
            error.value = 'Связь прервана. Повторите удаление после подключения.';
            return false;
        },
    });
}
</script>

<template>
    <ConfirmationDialog
        heading="Подтверждение удаления"
        :title="'Удалить «' + project.name + '»?'"
        description="После удаления восстановить проект нельзя."
        action="Удалить проект"
        destructive
        :processing="form.processing"
        :error="error"
        @confirm="destroy"
    >
        <template #trigger>
            <Button type="button" variant="destructive" class="h-11 shrink-0" :disabled="disabled" @click="error = ''">Удалить проект</Button>
        </template>
        <div class="studio-row">
            <dt>Проект</dt>
            <dd>{{ project.name }} · {{ project.content_mode === 'series' ? 'сериал' : 'короткие ролики' }}</dd>
        </div>
        <div class="studio-row">
            <dt>Активные задания</dt>
            <dd>{{ project.blocking_runs_count || 'Нет' }}</dd>
        </div>
    </ConfirmationDialog>
</template>
