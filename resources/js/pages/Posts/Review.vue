<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ProjectSummary } from '@/types/projects';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useFormApi } from '@/lib/api/useFormApi';
import { useRoute } from '@/composables/useRoute';

const props = defineProps<{
    id: number;
    title: string;
    channel: string;
    platform: string;
    project: Pick<ProjectSummary, 'name' | 'cover'>;
    assemblyNumber: number | null;
    submittedAt: string | null;
}>();
const submitForm = useFormApi();
const route = useRoute();
const requestError = ref('');
const form = useForm({ external_id: '' });
function verify() {
    requestError.value = '';

    submitForm(form, ['posts.requests.resolve', props.id], {
        preserveScroll: true,

        onNetworkError: () => {
            requestError.value = 'Связь прервана. Повторите проверку после подключения.';
            return false;
        },
        onHttpException: (response) => {
            if (response.status === 419) return;
            requestError.value = 'Не удалось сверить публикацию. Повторите проверку.';
            return false;
        },
    });
}
</script>

<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Публикации', href: route('posts') },
            { title: 'Сверка публикации', href: route('posts.requests.review', id) },
        ]"
    >
        <Head title="Сверка публикации" />
        <div class="workspace-page">
            <Heading title="Сверка публикации" :description="title" />
            <form class="studio-panel grid max-w-3xl gap-4" @submit.prevent="verify">
                <p role="status">Запрос отправлен. Площадка пока не подтвердила результат.</p>
                <dl>
                    <div class="studio-row">
                        <dt>Канал</dt>
                        <dd>{{ channel }} · {{ platform }}</dd>
                    </div>
                    <div class="studio-row">
                        <dt>Проект</dt>
                        <dd>{{ project.name }}</dd>
                    </div>
                    <div v-if="assemblyNumber !== null" class="studio-row">
                        <dt>Сборка</dt>
                        <dd>Версия {{ assemblyNumber }}</dd>
                    </div>
                    <div v-if="submittedAt" class="studio-row">
                        <dt>Последняя отправка</dt>
                        <dd>
                            <time :datetime="submittedAt">{{ new Date(submittedAt).toLocaleString('ru') }}</time>
                        </dd>
                    </div>
                </dl>
                <div class="grid gap-2">
                    <Label for="reference">Ссылка или ID опубликованного видео</Label>
                    <Input id="reference" v-model="form.external_id" required maxlength="2048" />
                </div>
                <p>Найдите ролик в указанном аккаунте. Poster проверит его статус без повторной отправки видео.</p>
                <p v-if="requestError || form.hasErrors" role="alert">{{ requestError || Object.values(form.errors).join(' ') }}</p>
                <Button class="justify-self-start" type="submit" :disabled="form.processing">Проверить публикацию</Button>
            </form>
        </div>
    </AppLayout>
</template>
