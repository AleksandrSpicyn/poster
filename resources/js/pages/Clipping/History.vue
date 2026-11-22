<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';
const route = useRoute();
defineProps<{
    sources: {
        id: number;
        clipping_connection_id: number;
        external_id: string;
        title: string | null;
        last_synced_at: string | null;
        sync_paused_at: string | null;
        sync_error: string | null;
        publications_pending: boolean;
    }[];
    imports: {
        id: number;
        clipping_connection_id: number;
        external_id: string;
        source_name: string | null;
        imported_at: string | null;
        clip_count: number;
    }[];
}>();
</script>
<template>
    <AppLayout
        :breadcrumbs="[
            { title: 'Источники', href: route('clipping.index') },
            { title: 'История импорта', href: route('clipping.history') },
        ]"
    >
        <Head title="История импорта" />
        <div class="workspace-page">
            <div class="flex flex-wrap justify-between gap-4">
                <Heading title="История импорта" description="Последние 100 предпросмотров и подтверждений" /><Button @click="router.reload()"
                    >Обновить</Button
                >
            </div>
            <section class="studio-panel" id="history">
                <h2>Область данных</h2>
                <p v-if="!imports.length">Импортов пока нет.</p>
                <article v-for="run in imports" :key="run.id" class="studio-row">
                    <Link :href="route('clipping.preview.show', [run.clipping_connection_id, run.id])" class="underline">{{
                        run.source_name || 'Проект без названия'
                    }}</Link>
                    <span>{{ run.imported_at ? 'Завершено' : 'Предпросмотр' }} · Клипов: {{ run.clip_count }}</span>
                </article>
            </section>
            <section v-if="sources.length" class="studio-panel">
                <h2>Обновление источников</h2>
                <article v-for="source in sources" :key="source.id" class="studio-row">
                    <div>
                        <strong>{{ source.title || 'Источник без названия' }}</strong>
                        <p v-if="source.last_synced_at">Последнее полное обновление · {{ new Date(source.last_synced_at).toLocaleString('ru') }}</p>
                    </div>
                    <div>
                        <div v-if="source.sync_paused_at">
                            <strong>Синхронизация приостановлена</strong>
                            <p>Проверьте доступ к подключению.</p>
                        </div>
                        <div v-else-if="source.publications_pending">
                            <strong>Частичное обновление</strong>
                            <p>Клипы сохранены. История публикаций пока не получена.</p>
                        </div>
                        <p v-if="source.sync_error && !source.sync_paused_at">Обновление продолжится, когда источник станет доступен.</p>
                        <Form
                            v-if="source.sync_paused_at"
                            :action="route('clipping.sources.resume', [source.clipping_connection_id, source.id])"
                            method="post"
                            v-slot="{ processing }"
                        >
                            <Button type="submit" variant="outline" :disabled="processing">Возобновить обновление</Button>
                        </Form>
                    </div>
                </article>
            </section>
        </div></AppLayout
    >
</template>
