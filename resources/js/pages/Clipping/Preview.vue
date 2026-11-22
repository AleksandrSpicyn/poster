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
        external_id: string;
        source_name: string | null;
        project_id: number | null;
        accounts: { id: string; name: string; external_id: string; channel_id: number | null }[] | null;
        imported_at: string | null;
    };
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
                <Button v-else-if="preview.project_id !== null" as-child
                    ><Link :href="route('projects.show', preview.project_id)">Открыть проект</Link></Button
                >
            </div>
            <p v-if="page.props.errors.project_id" role="alert" class="text-destructive">{{ page.props.errors.project_id }}</p>
            <section class="studio-panel">
                <h2>Источник → Poster</h2>
                <div class="studio-row">
                    <strong>{{ preview.source_name || preview.external_id }}</strong
                    ><span>{{ projectName || 'Новый проект' }}</span>
                </div>
                <div v-for="account in preview.accounts" :key="account.id" class="studio-row">
                    <span>{{ account.name || account.external_id }}</span
                    ><span>{{ account.channel_id ? 'Канал №' + account.channel_id : 'Создать канал без права отправки' }}</span>
                </div>
                <p v-if="page.props.errors.accounts" role="alert" class="text-destructive">{{ page.props.errors.accounts }}</p>
            </section>
            <section class="studio-panel">
                <h2>Клипы · {{ clips.length }}</h2>
                <p v-if="!clips.length">Источник не содержит клипов.</p>
                <article v-for="clip in clips" :key="clip.id" class="studio-row">
                    <div>
                        <strong>{{ clip.title || clip.id }}</strong>
                        <p>{{ clip.id }}</p>
                    </div>
                    <div>
                        <p v-for="(post, index) in clip.publications" :key="index">{{ post.platform }} · {{ post.status || 'Статус неизвестен' }}</p>
                    </div>
                </article>
            </section>
        </div>
    </AppLayout>
</template>
