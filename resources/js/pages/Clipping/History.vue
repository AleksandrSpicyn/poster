<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/vue3';
import { useRoute } from 'ziggy-js';
const route = useRoute();
defineProps<{
    sources: {
        id: number;
        external_id: string;
        title: string | null;
        last_synced_at: string | null;
        sync_paused_at: string | null;
        sync_error: string | null;
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
                        run.source_name || run.external_id
                    }}</Link>
                    <span>{{ run.imported_at ? 'Завершено' : 'Предпросмотр' }} · Клипов: {{ run.clip_count }}</span>
                </article>
            </section>
        </div></AppLayout
    >
</template>
