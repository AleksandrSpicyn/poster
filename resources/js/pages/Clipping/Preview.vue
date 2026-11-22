<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import { useRoute } from 'ziggy-js';

const props = defineProps<{
    connection: { id: number; name: string };
    preview: {
        id: number;
        external_id: string | null;
        source_name: string | null;
        project_id: number | null;
        accounts: { id: string; name: string; external_id: string; channel_id: number | null }[] | null;
        imported_at: string | null;
    };
    accountNames: Record<string, string>;
    projectName: string | null;
    clips: {
        id: string;
        title: string | null;
        durationMs: number | null;
        publications: { platform: string; status: string | null; url: string | null }[];
    }[];
}>();
const route = useRoute();
const form = useForm({});
const page = usePage<SharedData>();
function confirm() {
    form.post(route('clipping.import', [props.connection.id, props.preview.id]));
}
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: 'Сопоставление', href: route('clipping.preview.show', [connection.id, preview.id]) },
        ]"
    >
        <Head title="Сопоставление импорта" />
        <div class="workspace-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Сопоставление импорта" :description="connection.name" />
                <Button v-if="!preview.imported_at" @click="confirm" :disabled="form.processing">Подтвердить импорт</Button>
                <Button v-else disabled>Подтвердить импорт</Button>
                <Button v-if="preview.imported_at && preview.project_id !== null" as-child
                    ><Link :href="route('projects.show', preview.project_id)">Открыть клипы проекта</Link></Button
                >
            </div>
            <section v-if="preview.imported_at" class="studio-panel space-y-2" role="status">
                <h2>Импорт уже подтверждён</h2>
                <p>{{ new Date(preview.imported_at).toLocaleString('ru-RU') }}. Те же клипы повторно не добавятся.</p>
                <div class="studio-row">
                    <span>Проект Poster</span><strong>{{ projectName }}</strong>
                </div>
                <div class="studio-row">
                    <span>Результат</span><strong>Клипов: {{ clips.length }} · Сопоставлений: {{ preview.accounts?.length ?? 0 }}</strong>
                </div>
            </section>
            <p v-if="page.props.errors.project_id" role="alert" class="text-destructive">{{ page.props.errors.project_id }}</p>
            <section class="studio-panel">
                <h2>Источник → Poster</h2>
                <div class="studio-row">
                    <strong>{{ preview.source_name || (preview.external_id ? 'Проект без названия' : 'Связи с аккаунтами') }}</strong><span>{{
                        projectName || 'Новый проект'
                    }}</span>
                </div>
                <div v-for="account in preview.accounts" :key="account.id" class="studio-row">
                    <span>{{ account.name || 'Аккаунт без названия' }}</span><span>{{
                        accountNames[account.id] || (preview.imported_at ? 'Без права отправки' : 'Создать канал без права отправки')
                    }}</span>
                </div>
                <p v-if="page.props.errors.accounts" role="alert" class="text-destructive">{{ page.props.errors.accounts }}</p>
            </section>
            <section v-if="preview.external_id" class="studio-panel">
                <h2>Клипы · {{ clips.length }}</h2>
                <div v-if="!clips.length" class="space-y-2">
                    <strong>В источнике нет клипов</strong>
                    <p>
                        {{
                            preview.imported_at
                                ? 'Источник сохранён. Новые клипы появятся после обновления.'
                                : 'Подтвердите импорт, чтобы сохранить источник для следующих обновлений.'
                        }}
                    </p>
                    <Link :href="route('clipping.sources', connection.id)" class="underline">Выбрать другой источник</Link>
                </div>
                <article v-for="clip in clips" :key="clip.id" class="studio-row">
                    <div>
                        <strong>{{ clip.title || 'Клип без названия' }}</strong>
                    </div>
                    <div>
                        <p v-for="(post, index) in clip.publications" :key="index">{{ post.platform }} · {{ post.status || 'Статус неизвестен' }}</p>
                    </div>
                </article>
            </section>
        </div>
    </AppLayout>
</template>
