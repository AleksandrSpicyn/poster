<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedData } from '@/types';
import { useRoute } from 'ziggy-js';

type Connection = { id: number; name: string; driver: string };
defineProps<{ connections: Connection[] }>();
const page = usePage<SharedData>();
const route = useRoute();
</script>
<template>
    <AppLayout :breadcrumbs="[{ title: 'Источники', href: route('clipping.index') }]">
        <Head title="Провайдеры клиппинга" />
        <div class="workspace-page">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <Heading title="Провайдеры клиппинга" description="Подключения этого пользователя" />
                <Button as-child><Link :href="route('clipping.create')">Добавить подключение</Link></Button>
            </div>
            <p v-if="page.props.errors.connection" role="alert" class="text-destructive">{{ page.props.errors.connection }}</p>
            <section class="studio-panel">
                <h2>Подключения</h2>
                <p v-if="!connections.length">Подключите провайдера для импорта проектов и клипов.</p>
                <article v-for="connection in connections" :key="connection.id" class="studio-row">
                    <div>
                        <strong>{{ connection.name }}</strong>
                        <p>{{ connection.driver }}</p>
                    </div>
                    <Link :href="route('clipping.sources', connection.id)" class="underline">Выбрать источник</Link>
                </article>
            </section>
            <Link :href="route('clipping.history')" class="underline">История импорта</Link>
        </div>
    </AppLayout>
</template>
