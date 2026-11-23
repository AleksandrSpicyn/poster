<script setup lang="ts">
import { useFormApi } from '@/lib/api/useFormApi';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{ projectId: number; archived: boolean }>();
const submitForm = useFormApi();
const archiveForm = useForm({ archived: false });
function toggleArchive() {
    archiveForm.archived = !props.archived;
    submitForm(archiveForm, ['projects.archive', props.projectId]);
}
</script>
<template>
    <section
        class="flex flex-col items-start justify-between gap-4 rounded-xl border p-5 sm:flex-row sm:items-center"
        aria-labelledby="archive-project-heading"
    >
        <h2 id="archive-project-heading" class="font-semibold">Архив проекта</h2>
        <Button variant="outline" :disabled="archiveForm.processing" @click="toggleArchive">{{
            archived ? 'Вернуть из архива' : 'Архивировать проект'
        }}</Button>
    </section>
</template>
